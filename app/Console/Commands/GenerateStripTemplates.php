<?php

namespace App\Console\Commands;

use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;

/**
 * Eenmalig batch-script om 30 fotostrip-achtergrond-templates te genereren via Gemini,
 * verdeeld over 6 categorieën. Resultaten gaan naar storage/app/public/template-previews/
 * voor beoordeling — dit is GEEN onderdeel van de live feature, puur een voorstel-batch.
 */
class GenerateStripTemplates extends Command
{
    protected $signature = 'templates:generate-preview-batch {--only=} {--start=1} {--slug=} {--from-index=1}';

    protected $description = 'Genereer een voorstel-batch van 30 fotostrip-templates (6 categorieën x 5) via Gemini';

    private const CANVAS_WIDTH = 600;

    private const CANVAS_HEIGHT = 1800;

    private const PROMPT_TEMPLATE = <<<'PROMPT'
Ontwerp een verticale achtergrondafbeelding voor een fotostrip-mockup, in fotostrip (verhouding exact 2:6). De afmeting minimaal 1600px hoog. Sfeervolle stijl passend bij het thema hieronder. Houd het midden rustig en de onderkant van de afbeelding leeg van elementen zodat daar later ruimte is om een tekst te plaatsen. Geen tekst, geen logo's, geen mensen. Hoge resolutie, scherp, geschikt om af te drukken.

Plaats eventuele decoratieve elementen (bloemen, figuren, patronen, motieven) vooral langs de linker- en rechterrand van de afbeelding, geleidelijk uitdunnend naar het midden toe. Het midden van de afbeelding blijft leeg en rustig: daar komen straks de foto's overheen te liggen, dus alles wat daar staat is niet zichtbaar in het eindresultaat.

Belangrijk: dit mag NOOIT resulteren in 3 losse zichtbare kolommen of vlakken (links-midden-rechts) met een harde lijn, rand of overgang ertussen — de overgang van de rand naar het lege midden moet volledig vloeiend en organisch aflopen, zoals een natuurlijke vervaging, zonder enige zichtbare scheidingslijn.

Geen harde elementen zoals kaders of voorwerpen behalve indien aangegeven in de omschrijving in thema hier onder. Het moet een rustige kleur zijn. Geen foto/ruimte. Gebruik zo veel mogelijk lichtte kleuren behalve indien anders aangegeven.

Indien er een afbeelding is bijgevoegd wil ik dat je die stijl over neemt. Misschien wat leuke elementen maar vooral de kleurtonen.

Je moet niks plaatsen van de vaste elementen van een fotostrip, dus geen kaders, geen randen, geen hoeken.

Geen harde randen of lijnen.

De afbeelding moet van boven naar beneden en van links naar rechts overal naadloos doorlopen, als één ononderbroken geheel — alsof het één groot oppervlak of tafereel is, geschilderd of gefotografeerd in één stuk, nooit uit meerdere aparte delen samengesteld. Gebruik nooit een tegel-, paneel-, kolom- of platenstructuur (dus geen aparte marmertegels, vloertegels, wandpanelen of verticale/horizontale stroken die naast of onder elkaar geplaatst lijken), ook niet als het thema hieronder een materiaal als marmer, steen of hout beschrijft. Zorg dat er nergens een zichtbare rechthoekige of vierkante vlek, band, kolom, paneel, tegelnaad of harde overgang in het beeld zit — ook niet onderaan, in het midden of aan de zijkanten. Geen zichtbare naden of scheidingslijnen waar dan ook.

Thema: {beschrijving}
PROMPT;

    /** @var array<string, array<int, array{slug: string, label: string, beschrijving: string}>> */
    private const CATEGORIES = [
        'bruiloft' => [
            ['slug' => 'romantische-rozen', 'label' => 'Romantische rozen', 'beschrijving' => 'Romantisch bloemendessin met zachte rozen en eucalyptus in ivoor en blush-tinten'],
            ['slug' => 'gouden-bladpatroon', 'label' => 'Gouden bladpatroon', 'beschrijving' => "Subtiele gouden bladpatronen op een crème achtergrond, elegant en tijdloos"],
            ['slug' => 'aquarel-champagne', 'label' => 'Aquarel champagne', 'beschrijving' => 'Zachte aquarel-waas in dusty pink en champagne, dromerig en luchtig'],
            ['slug' => 'marmer-goud', 'label' => 'Marmer & goud', 'beschrijving' => 'Minimalistisch marmer-effect in wit en zacht goud, chic en verfijnd. Geschilderd als één doorlopend oppervlak, geen aparte marmertegels of platen naast elkaar — geen tegelnaden.'],
            ['slug' => 'waterverf-bloesem', 'label' => 'Waterverf bloesem', 'beschrijving' => 'Losse waterverf-bloesems in lavendel en zalm, romantisch lentegevoel'],
            ['slug' => 'minimalistisch-wit', 'label' => 'Minimalistisch wit', 'beschrijving' => 'Strak minimalistisch wit met een subtiele textuur, modern en sereen'],
            ['slug' => 'boho-eucalyptus', 'label' => 'Boho eucalyptus', 'beschrijving' => 'Bohemian eucalyptus-takken in zachte groentinten, natuurlijk en luchtig'],
            ['slug' => 'blush-marmer', 'label' => 'Blush marmer', 'beschrijving' => 'Zacht blush-roze marmer-effect, romantisch en verfijnd'],
            ['slug' => 'gouden-ringen-motief', 'label' => 'Gouden ringen-motief', 'beschrijving' => 'Subtiel gouden lijnenspel geïnspireerd op trouwringen, elegant'],
            ['slug' => 'lavendel-droom', 'label' => 'Lavendel droom', 'beschrijving' => 'Dromerige lavendelvelden-waas in zacht paars en groen'],
            ['slug' => 'parelmoer-glans', 'label' => 'Parelmoer glans', 'beschrijving' => 'Subtiele parelmoer-glans in wit en zacht roze'],
            ['slug' => 'wilde-bloemen-veld', 'label' => 'Wilde bloemen veld', 'beschrijving' => 'Losse wilde bloemen in zachte aquarel-tinten, landelijk romantisch'],
            ['slug' => 'champagne-confetti', 'label' => 'Champagne confetti', 'beschrijving' => 'Zachte champagne-gouden confetti-spatten, feestelijk elegant'],
            ['slug' => 'zijde-textuur-ivoor', 'label' => 'Zijde textuur ivoor', 'beschrijving' => 'Zachte zijde-textuur in ivoor met een subtiele glans'],
            ['slug' => 'bruidsboeket-waas', 'label' => 'Bruidsboeket waas', 'beschrijving' => 'Zachte wazige bloemenprint geïnspireerd op een bruidsboeket'],
            ['slug' => 'gouden-cirkel-motief', 'label' => 'Gouden cirkel-motief', 'beschrijving' => 'Minimalistische gouden cirkel-motieven op crème achtergrond'],
            ['slug' => 'roze-goud-waterverf', 'label' => 'Roze-goud waterverf', 'beschrijving' => 'Zachte roze-gouden waterverfwolken, luxueus en romantisch'],
            ['slug' => 'pioenroos-waterverf', 'label' => 'Pioenroos waterverf', 'beschrijving' => 'Zachte waterverf pioenrozen in blush en crème, romantisch'],
            ['slug' => 'linnen-textuur-ivoor', 'label' => 'Linnen textuur ivoor', 'beschrijving' => 'Subtiele linnen-textuur in ivoor, natuurlijk en verfijnd'],
            ['slug' => 'gouden-veren-motief', 'label' => 'Gouden veren-motief', 'beschrijving' => 'Subtiel gouden veren-motief, licht en elegant'],
            ['slug' => 'viooltjes-lila-zacht', 'label' => 'Viooltjes lila', 'beschrijving' => 'Kleine viooltjes in zacht lila en groen, romantisch lentegevoel'],
            ['slug' => 'lelietjes-van-dalen-wit', 'label' => 'Lelietjes-van-dalen', 'beschrijving' => 'Tere lelietjes-van-dalen in wit en zachtgroen, romantisch fris'],
            ['slug' => 'gouden-confetti-elegant', 'label' => 'Gouden confetti elegant', 'beschrijving' => 'Subtiele gouden confetti-spikkels op crème, elegant feestelijk'],
            ['slug' => 'ivoor-kant-motief', 'label' => 'Ivoor kant motief', 'beschrijving' => 'Subtiel ivoorkleurig kant-geïnspireerd motief, romantisch verfijnd'],
        ],
        'verjaardag' => [
            ['slug' => 'pastel-glans', 'label' => 'Pastel glans', 'beschrijving' => 'Vrolijke pastel gradiënt met een zachte glans, feestelijk en fris'],
            ['slug' => 'aquarel-confetti', 'label' => 'Aquarel confetti', 'beschrijving' => 'Speelse aquarel-confetti-vlekken in vrolijke, felle kleuren'],
            ['slug' => 'zonsondergang', 'label' => 'Warme zonsondergang', 'beschrijving' => 'Warme zonsondergang-gradiënt in oranje, roze en geel'],
            ['slug' => 'speelse-vormen', 'label' => 'Speelse vormen', 'beschrijving' => 'Kleurrijke abstracte vormen met een speelse, kinderlijke sfeer'],
            ['slug' => 'regenboog-glinster', 'label' => 'Regenboog glinster', 'beschrijving' => 'Zacht regenboog-verloop met een lichte glinstering, vrolijk en fris'],
            ['slug' => 'ballonnen-silhouet', 'label' => 'Ballonnen silhouet', 'beschrijving' => 'Zachte ballon-silhouetten in vrolijke pastelkleuren tegen een lichte lucht'],
            ['slug' => 'slingers-feestelijk', 'label' => 'Slingers feestelijk', 'beschrijving' => 'Speelse feestslingers-motief in vrolijke kleuren aan de bovenrand'],
            ['slug' => 'taart-pastel', 'label' => 'Taart pastel', 'beschrijving' => 'Zachte pastel roze en geel tinten met een speelse, zoete sfeer'],
            ['slug' => 'sterren-confetti-goud', 'label' => 'Sterren confetti goud', 'beschrijving' => 'Gouden sterren-confetti op een zachte crème achtergrond'],
            ['slug' => 'neon-verjaardag', 'label' => 'Neon verjaardag', 'beschrijving' => 'Vrolijke neon-kleurverloop in roze en turquoise, energiek'],
            ['slug' => 'cadeaupapier-motief', 'label' => 'Cadeaupapier-motief', 'beschrijving' => 'Speels cadeaupapier-geïnspireerd patroon in vrolijke kleuren'],
            ['slug' => 'vlaggetjes-feest', 'label' => 'Vlaggetjes feest', 'beschrijving' => 'Kleine vrolijke vlaggetjes-slingers-motief aan de randen'],
            ['slug' => 'glitter-verjaardag-goud', 'label' => 'Glitter verjaardag goud', 'beschrijving' => 'Sprankelende gouden glitter-gradiënt, feestelijk'],
            ['slug' => 'macarons-pastel', 'label' => 'Macarons pastel', 'beschrijving' => 'Zachte macaron-geïnspireerde pastelkleuren, zoet en vrolijk'],
            ['slug' => 'vuurwerk-verjaardag', 'label' => 'Vuurwerk verjaardag', 'beschrijving' => 'Speels vuurwerk-effect in vrolijke felle kleuren'],
            ['slug' => 'wolkjes-vrolijk-pastel', 'label' => 'Wolkjes vrolijk pastel', 'beschrijving' => 'Vrolijke pastel wolkjes-motief met een zachte regenboog'],
            ['slug' => 'retro-verjaardag-print', 'label' => 'Retro verjaardag print', 'beschrijving' => 'Speels retro patroon met vrolijke geometrische vormen'],
            ['slug' => 'slingerlampjes-feest', 'label' => 'Slingerlampjes feest', 'beschrijving' => 'Speelse feestlampjes-slinger-motief in warme kleuren'],
            ['slug' => 'confetti-regen-vrolijk', 'label' => 'Confetti regen', 'beschrijving' => 'Vrolijke confetti-regen in felle kleuren, feestelijk'],
            ['slug' => 'verjaardagskaars-motief', 'label' => 'Verjaardagskaars-motief', 'beschrijving' => 'Speels verjaardagskaarsjes-motief in pastelkleuren'],
            ['slug' => 'cupcake-kaarsjes-vrolijk', 'label' => 'Cupcake kaarsjes', 'beschrijving' => 'Speelse cupcake met kaarsjes-motief, vrolijk zoet'],
            ['slug' => 'sterren-slinger-verjaardag', 'label' => 'Sterren slinger', 'beschrijving' => 'Vrolijke sterren-slinger-motief in pastelkleuren'],
            ['slug' => 'verjaardag-cadeauband-vrolijk', 'label' => 'Cadeauband vrolijk', 'beschrijving' => 'Speels cadeauband-motief in vrolijke feestkleuren'],
        ],
        'bedrijfsfeest' => [
            ['slug' => 'marine-zilver', 'label' => 'Marineblauw & zilver', 'beschrijving' => 'Strak modern verloop in marineblauw en zilver, zakelijk en stijlvol'],
            ['slug' => 'geometrisch-koper', 'label' => 'Geometrisch koper', 'beschrijving' => 'Subtiel geometrisch patroon in antraciet en koper tinten'],
            ['slug' => 'premium-donker', 'label' => 'Premium donker', 'beschrijving' => 'Elegante donkere achtergrond met zachte lichtvlekken, premium gevoel'],
            ['slug' => 'grijs-goud', 'label' => 'Grijs met goud', 'beschrijving' => 'Minimalistisch verloop in grijstinten met een vleugje goud'],
            ['slug' => 'petrol-golven', 'label' => 'Petrol golven', 'beschrijving' => 'Moderne abstracte golven in petrol en zilver, professioneel en fris'],
            ['slug' => 'zakelijk-blauw-lijnen', 'label' => 'Zakelijk blauw lijnen', 'beschrijving' => 'Strakke lijnen-textuur in staalblauw, modern en zakelijk'],
            ['slug' => 'minimalistisch-wit-zilver', 'label' => 'Minimalistisch wit-zilver', 'beschrijving' => 'Strak minimalistisch wit met zilveren accenten'],
            ['slug' => 'donkerblauw-goud-luxe', 'label' => 'Donkerblauw goud luxe', 'beschrijving' => 'Luxueus donkerblauw met fijne gouden lijnen'],
            ['slug' => 'modern-abstract-grijs', 'label' => 'Modern abstract grijs', 'beschrijving' => 'Modern abstract verloop in verschillende grijstinten'],
            ['slug' => 'koper-lijnen-modern', 'label' => 'Koper lijnen modern', 'beschrijving' => 'Subtiele koperkleurige lijnenpatronen op een lichte achtergrond'],
            ['slug' => 'corporate-groen-zilver', 'label' => 'Corporate groen-zilver', 'beschrijving' => 'Fris zakelijk groen met zilveren accenten'],
            ['slug' => 'glas-textuur-blauw', 'label' => 'Glas textuur blauw', 'beschrijving' => 'Subtiele glas-achtige textuur in helder blauw'],
            ['slug' => 'antraciet-goud-elegant', 'label' => 'Antraciet goud elegant', 'beschrijving' => 'Elegant antraciet met fijne gouden details'],
            ['slug' => 'netwerk-patroon-modern', 'label' => 'Netwerk patroon modern', 'beschrijving' => 'Subtiel modern netwerk/lijnenpatroon in staalgrijs'],
            ['slug' => 'donker-marmer-zakelijk', 'label' => 'Donker marmer zakelijk', 'beschrijving' => 'Chic donker marmer-effect in zwart en grijs. Geschilderd als één doorlopend oppervlak, geen aparte marmertegels of platen naast elkaar — geen tegelnaden.'],
            ['slug' => 'zachte-blauwe-golven-zakelijk', 'label' => 'Zachte blauwe golven', 'beschrijving' => 'Kalme zachte blauwe golvenpatroon, professioneel'],
            ['slug' => 'premium-wit-goud-zakelijk', 'label' => 'Premium wit-goud', 'beschrijving' => 'Premium wit met subtiele gouden accentlijnen'],
            ['slug' => 'staalblauw-verloop', 'label' => 'Staalblauw verloop', 'beschrijving' => 'Strak zakelijk verloop in staalblauw en wit, modern'],
            ['slug' => 'platina-lijnen', 'label' => 'Platina lijnen', 'beschrijving' => 'Subtiele platina-kleurige lijnenpatronen op lichtgrijs'],
            ['slug' => 'nachtblauw-sterren-zakelijk', 'label' => 'Nachtblauw sterren', 'beschrijving' => 'Diep nachtblauw met subtiele sterren-lichtpuntjes, chic zakelijk'],
            ['slug' => 'warm-brons-modern', 'label' => 'Warm brons modern', 'beschrijving' => 'Modern warm brons verloop met zachte lichtvlekken'],
            ['slug' => 'titanium-grijs-modern', 'label' => 'Titanium grijs', 'beschrijving' => 'Modern titanium-grijs verloop met subtiele lichtreflecties, strak zakelijk'],
            ['slug' => 'saffier-blauw-elegant', 'label' => 'Saffier blauw', 'beschrijving' => 'Elegant saffierblauw verloop met fijne zilveren accenten'],
            ['slug' => 'zwart-koper-luxe', 'label' => 'Zwart-koper luxe', 'beschrijving' => 'Luxueus zwart met warme koperkleurige accentlijnen'],
            ['slug' => 'helder-glas-modern-zakelijk', 'label' => 'Helder glas modern', 'beschrijving' => 'Subtiele heldere glasachtige textuur in lichtblauw, fris zakelijk'],
            ['slug' => 'zilver-lijnen-elegant-zakelijk', 'label' => 'Zilver lijnen elegant', 'beschrijving' => 'Elegante fijne zilveren lijnenpatronen op wit, strak zakelijk'],
            ['slug' => 'donkergrijs-koper-modern', 'label' => 'Donkergrijs koper modern', 'beschrijving' => 'Modern donkergrijs verloop met warme koperen accenten'],
        ],
        'kerst-winter' => [
            ['slug' => 'sneeuwval-bokeh', 'label' => 'Sneeuwval bokeh', 'beschrijving' => 'Zachte sneeuwval met een warme, besneeuwde bokeh-gloed'],
            ['slug' => 'kerstgroen-bordeaux', 'label' => 'Kerstgroen & bordeaux', 'beschrijving' => 'Kerstgroen en bordeaux in een zachte aquarel-stijl'],
            ['slug' => 'ijzig-blauw', 'label' => 'IJzig blauw', 'beschrijving' => 'IJzige blauwtinten met subtiele glinsterende sneeuwvlokken'],
            ['slug' => 'kaarsengloed', 'label' => 'Kaarsengloed', 'beschrijving' => 'Warme kaarsengloed-sfeer in amber en donkerrood'],
            ['slug' => 'dennengroen-goud', 'label' => 'Dennengroen & goud', 'beschrijving' => 'Winters dennengroen met zachte gouden accenten, gezellig en warm'],
            ['slug' => 'gouden-sneeuwvlokken', 'label' => 'Gouden sneeuwvlokken', 'beschrijving' => 'Fijne gouden sneeuwvlok-motieven op een zachte crèmekleurige ondergrond'],
            ['slug' => 'kerststerren-rood', 'label' => 'Kerststerren rood', 'beschrijving' => 'Zachte kerstster (poinsettia) bloemen in rood en groen, aquarel-stijl'],
            ['slug' => 'winterse-mistletoe', 'label' => 'Winterse mistletoe', 'beschrijving' => 'Subtiele maretak-takjes met rode besjes in een zachte waas'],
            ['slug' => 'besneeuwde-dennen', 'label' => 'Besneeuwde dennen', 'beschrijving' => 'Zachte besneeuwde dennentakken-silhouetten aan de randen'],
            ['slug' => 'kerst-plaid-textuur', 'label' => 'Kerst plaid-textuur', 'beschrijving' => 'Warme rood-groene geruite plaid-textuur, gezellig winters'],
            ['slug' => 'zilveren-sneeuwstorm', 'label' => 'Zilveren sneeuwstorm', 'beschrijving' => 'IJle zilverkleurige sneeuwstorm-textuur, koel en elegant'],
            ['slug' => 'warme-kerst-gloed', 'label' => 'Warme kerstgloed', 'beschrijving' => 'Zachte warme amber gloed met een vleugje kerstlichtjes-bokeh'],
            ['slug' => 'kerst-lint-goud', 'label' => 'Kerst lint goud', 'beschrijving' => 'Speelse gouden lint-motieven, feestelijk en elegant'],
            ['slug' => 'wintertak-aquarel', 'label' => 'Wintertak aquarel', 'beschrijving' => 'Losse winter-aquarel takjes in wit en zilvergroen'],
            ['slug' => 'kerstbal-motief', 'label' => 'Kerstbal motief', 'beschrijving' => 'Subtiele kerstbal-silhouetten in rood en goud, feestelijk'],
            ['slug' => 'poollicht-groen', 'label' => 'Poollicht groen', 'beschrijving' => 'Dromerig noorderlicht-effect in groen en paars'],
            ['slug' => 'kerst-kaneel-warm', 'label' => 'Kerst kaneel warm', 'beschrijving' => 'Warme kaneelbruine en amber tinten, gezellig kerstgevoel'],
            ['slug' => 'besneeuwd-dorpje', 'label' => 'Besneeuwd dorpje', 'beschrijving' => 'Zachte wazige besneeuwde dorpssilhouetten aan de onderrand'],
            ['slug' => 'gouden-ster-kerstnacht', 'label' => 'Gouden ster kerstnacht', 'beschrijving' => 'Diepblauwe kerstnacht met een stralende gouden ster'],
            ['slug' => 'kerst-framboos-goud', 'label' => 'Kerst framboos-goud', 'beschrijving' => 'Frisse framboosrood met gouden accenten, feestelijk elegant'],
            ['slug' => 'winterse-eucalyptus', 'label' => 'Winterse eucalyptus', 'beschrijving' => 'Winterse eucalyptus-takken met zilveren accenten'],
            ['slug' => 'sneeuw-en-kaarslicht', 'label' => 'Sneeuw en kaarslicht', 'beschrijving' => 'Zachte combinatie van sneeuw-textuur en warme kaarsengloed'],
            ['slug' => 'kerst-rood-wit-streep', 'label' => 'Kerst rood-wit streep', 'beschrijving' => 'Speelse zachte rood-witte streepjes, klassiek kerstgevoel'],
            ['slug' => 'ijskristallen-blauw', 'label' => 'IJskristallen blauw', 'beschrijving' => 'Fijne ijskristal-patronen in helder ijsblauw'],
            ['slug' => 'gezellige-kerstmarkt', 'label' => 'Gezellige kerstmarkt', 'beschrijving' => 'Warme sfeervolle waas geïnspireerd op een kerstmarkt, amber en bruin'],
        ],
        'gala-glamour' => [
            ['slug' => 'zwart-fluweel', 'label' => 'Zwart fluweel', 'beschrijving' => 'Diep zwart fluweel-effect met subtiele gouden glitter'],
            ['slug' => 'champagne-goud', 'label' => 'Champagne goud', 'beschrijving' => 'Luxueuze champagne-gouden gradiënt met een zachte glans'],
            ['slug' => 'smaragd-goud', 'label' => 'Smaragd & goud', 'beschrijving' => 'Donker smaragdgroen met fijne gouden accenten, chic en rijk'],
            ['slug' => 'zilver-paars', 'label' => 'Zilver op paars', 'beschrijving' => 'Zacht zilveren glitter-effect op een dieppaarse ondergrond'],
            ['slug' => 'marmer-zwart-goud', 'label' => 'Marmer zwart-goud', 'beschrijving' => 'Elegant marmer in zwart en goud, weelderig en verfijnd'],
            ['slug' => 'dieprood-fluweel-goud', 'label' => 'Dieprood fluweel-goud', 'beschrijving' => 'Dieprood fluweel-effect met fijne gouden glitter, weelderig'],
            ['slug' => 'platina-glinster', 'label' => 'Platina glinster', 'beschrijving' => 'Zachte platina glitter-gradiënt op donkergrijs, luxueus'],
            ['slug' => 'nachtblauw-sterren-glamour', 'label' => 'Nachtblauw sterren', 'beschrijving' => 'Diep nachtblauw met sprankelende sterren-glitter, glamoureus'],
            ['slug' => 'bordeaux-goud-luxe', 'label' => 'Bordeaux goud luxe', 'beschrijving' => 'Rijk bordeauxrood met fijne gouden accenten, chic'],
            ['slug' => 'zwart-goud-art-deco', 'label' => 'Zwart-goud art deco', 'beschrijving' => 'Elegant zwart met gouden art-deco geïnspireerde lijnen'],
            ['slug' => 'zilver-confetti-zwart', 'label' => 'Zilver confetti op zwart', 'beschrijving' => 'Zilveren confetti-spikkels op een diepzwarte ondergrond'],
            ['slug' => 'paars-fluweel-glinster', 'label' => 'Paars fluweel glinster', 'beschrijving' => 'Diep paars fluweel-effect met subtiele zilveren glitter'],
            ['slug' => 'champagne-bubbels-glamour', 'label' => 'Champagne bubbels', 'beschrijving' => 'Luxueuze champagnebubbels-textuur in goud en crème'],
            ['slug' => 'donkergroen-goud-weelde', 'label' => 'Donkergroen goud weelde', 'beschrijving' => 'Weelderig donkergroen met fijne gouden details'],
            ['slug' => 'rose-gold-glitter-avond', 'label' => 'Rosé-gouden glitter avond', 'beschrijving' => 'Sprankelende rosé-gouden glitter-gradiënt, avondlijk chic'],
            ['slug' => 'zwart-zilver-streep-elegant', 'label' => 'Zwart-zilver streep', 'beschrijving' => 'Elegante zachte zilveren streeppatronen op zwart'],
            ['slug' => 'kobalt-blauw-glinster', 'label' => 'Kobalt blauw glinster', 'beschrijving' => 'Rijk kobaltblauw met sprankelende zilveren glitter, chic'],
            ['slug' => 'brons-fluweel-avond', 'label' => 'Brons fluweel avond', 'beschrijving' => 'Warm brons fluweel-effect met een zachte glans, avondlijk luxueus'],
        ],
        'zomer-tropisch' => [
            ['slug' => 'tropische-aquarel', 'label' => 'Tropische aquarel', 'beschrijving' => 'Frisse tropische aquarel in koraal, turquoise en zacht geel'],
            ['slug' => 'palm-silhouet', 'label' => 'Palm silhouet', 'beschrijving' => "Zachte zonnige gradiënt met een vleugje palmboom-silhouet aan de randen"],
            ['slug' => 'citrus-tinten', 'label' => 'Citrus tinten', 'beschrijving' => 'Vrolijke citrus-tinten in geel, oranje en munt'],
            ['slug' => 'zee-blauw-schuim', 'label' => 'Zee blauw & schuim', 'beschrijving' => 'Zacht zee-blauw met een vleugje schuim en zonlicht'],
            ['slug' => 'tropische-bloemen', 'label' => 'Tropische bloemen', 'beschrijving' => 'Tropische bloemenwaas in fel roze en groen, vrolijk zomers'],
            ['slug' => 'ananas-motief-fris', 'label' => 'Ananas motief', 'beschrijving' => 'Speels ananas-motief in geel en groen, fris zomers'],
            ['slug' => 'koraal-waterverf', 'label' => 'Koraal waterverf', 'beschrijving' => 'Zachte koraalkleurige waterverf-waas, warm zomers'],
            ['slug' => 'cocktail-tropisch', 'label' => 'Cocktail tropisch', 'beschrijving' => 'Vrolijke tropische cocktail-geïnspireerde kleuren, fris'],
            ['slug' => 'zonnig-strand-waas', 'label' => 'Zonnig strand', 'beschrijving' => 'Zachte zonnige strand-gradiënt in zand en blauw'],
            ['slug' => 'hibiscus-bloemen-tropisch', 'label' => 'Hibiscus bloemen', 'beschrijving' => 'Kleurrijke hibiscus-bloemen in roze en groen'],
            ['slug' => 'turquoise-golven-zomer', 'label' => 'Turquoise golven', 'beschrijving' => 'Frisse turquoise golvenpatroon, luchtig zomers'],
            ['slug' => 'watermeloen-fris-roze', 'label' => 'Watermeloen fris', 'beschrijving' => 'Vrolijke watermeloen-geïnspireerde tinten in roze en groen'],
            ['slug' => 'flamingo-silhouet-tropisch', 'label' => 'Flamingo silhouet', 'beschrijving' => 'Zachte flamingo-silhouetten tegen een zonnige gradiënt'],
            ['slug' => 'limoen-groen-fris', 'label' => 'Limoen groen', 'beschrijving' => 'Frisse limoengroene tinten met een vleugje geel'],
            ['slug' => 'zonsondergang-tropisch', 'label' => 'Zonsondergang tropisch', 'beschrijving' => 'Warme tropische zonsondergang-gradiënt in oranje en roze'],
            ['slug' => 'palmblad-aquarel', 'label' => 'Palmblad aquarel', 'beschrijving' => 'Losse aquarel palmbladeren in fris groen'],
            ['slug' => 'schelpen-strand-zomer', 'label' => 'Schelpen strand', 'beschrijving' => 'Zachte schelpenmotief in zand- en zeetinten'],
            ['slug' => 'surfboard-silhouet-zomer', 'label' => 'Surfboard silhouet', 'beschrijving' => 'Speelse surfboard-silhouetten tegen een zonnige gradiënt'],
            ['slug' => 'tropisch-blad-waterverf', 'label' => 'Tropisch blad waterverf', 'beschrijving' => 'Losse waterverf tropische bladeren in fris groen'],
            ['slug' => 'ijslolly-fris-zomer', 'label' => 'IJslolly fris', 'beschrijving' => 'Vrolijke ijslolly-geïnspireerde kleuren, fris zomers'],
            ['slug' => 'zonnebril-silhouet-zomer', 'label' => 'Zonnebril silhouet', 'beschrijving' => 'Speelse zonnebril-silhouetten, vrolijk zomers'],
            ['slug' => 'zomerbries-waterverf', 'label' => 'Zomerbries waterverf', 'beschrijving' => 'Zachte zomerbries-geïnspireerde waterverf-waas in blauw en geel'],
            ['slug' => 'tropische-vogel-silhouet', 'label' => 'Tropische vogel', 'beschrijving' => 'Kleurrijke tropische vogel-silhouetten, vrolijk zomers'],
        ],
        'vrijgezellenfeest' => [
            ['slug' => 'roze-goud-glinster', 'label' => 'Roze goud glinster', 'beschrijving' => 'Speelse roze-gouden glitter-gradiënt, feestelijk en glamoureus'],
            ['slug' => 'pastel-boho', 'label' => 'Pastel boho', 'beschrijving' => 'Zachte boho-waas in terracotta en beige met subtiele pluim-motieven'],
            ['slug' => 'bubbels-goud', 'label' => 'Bubbels & goud', 'beschrijving' => 'Vrolijke champagnebubbels-textuur in zacht goud en crème'],
            ['slug' => 'zwart-rose-gold', 'label' => 'Zwart & rosé-goud', 'beschrijving' => 'Stijlvol zwart met fijne rosé-gouden glitter-accenten'],
            ['slug' => 'pastel-confetti-roze', 'label' => 'Pastel confetti roze', 'beschrijving' => 'Losse pastel-confetti in roze en goud tinten, speels en chic'],
            ['slug' => 'tropisch-vrijgezellen', 'label' => 'Tropisch vrijgezellen', 'beschrijving' => 'Speelse tropische aquarel in koraal en goud, feestelijke vrijgezellensfeer'],
            ['slug' => 'glitter-wit-goud-vrijgezellen', 'label' => 'Glitter wit-goud', 'beschrijving' => 'Sprankelende witte glitter-gradiënt met goud, elegant feestelijk'],
            ['slug' => 'team-bride-roze', 'label' => 'Team bride roze', 'beschrijving' => 'Speels "team bride" geïnspireerd roze-wit patroon, feestelijk'],
            ['slug' => 'lingerie-kant-motief', 'label' => 'Kant motief', 'beschrijving' => 'Subtiel kant-geïnspireerd motief in zacht roze, chic'],
            ['slug' => 'cocktail-glamour-vrijgezellen', 'label' => 'Cocktail glamour', 'beschrijving' => 'Vrolijke cocktailglazen-silhouetten in goud en roze'],
            ['slug' => 'bloemenkroon-boho', 'label' => 'Bloemenkroon boho', 'beschrijving' => 'Zachte bloemenkroon-motief in boho-tinten, romantisch'],
            ['slug' => 'confetti-goud-wit-vrijgezellen', 'label' => 'Confetti goud-wit', 'beschrijving' => 'Feestelijke gouden en witte confetti-spatten'],
            ['slug' => 'palmboom-glinster-vrijgezellen', 'label' => 'Palmboom glinster', 'beschrijving' => 'Tropische palmboom-silhouetten met gouden glinster'],
            ['slug' => 'sterren-vrijgezellen-nacht', 'label' => 'Sterren vrijgezellen nacht', 'beschrijving' => 'Speelse sterrenhemel in donkerblauw en roze glitter'],
            ['slug' => 'pompons-feestelijk-roze', 'label' => 'Pompons feestelijk', 'beschrijving' => 'Speelse pompon-motief in roze en goud, vrolijk feestelijk'],
            ['slug' => 'cactus-boho-vrijgezellen', 'label' => 'Cactus boho', 'beschrijving' => 'Speelse boho cactus-silhouetten in terracotta en zand'],
            ['slug' => 'diamant-glinster-vrijgezellen', 'label' => 'Diamant glinster', 'beschrijving' => 'Fijne diamant/kristal-glinster-motief in zacht roze'],
            ['slug' => 'rose-gold-confetti-vrijgezellen', 'label' => 'Rosé-goud confetti', 'beschrijving' => 'Sprankelende rosé-gouden confetti, feestelijk'],
            ['slug' => 'boho-veren-vrijgezellen', 'label' => 'Boho veren', 'beschrijving' => 'Zachte boho-veren-motief in terracotta en crème'],
            ['slug' => 'sterren-glinster-roze-vrijgezellen', 'label' => 'Sterren glinster roze', 'beschrijving' => 'Speelse sterren-glinster in zacht roze en goud'],
            ['slug' => 'goud-confetti-glinster-vrijgezellen', 'label' => 'Goud confetti glinster', 'beschrijving' => 'Sprankelende gouden confetti-glinster, feestelijk chic'],
            ['slug' => 'boho-maan-vrijgezellen', 'label' => 'Boho maan', 'beschrijving' => 'Zachte boho maan-silhouet motief in terracotta en crème'],
        ],
        'babyshower' => [
            ['slug' => 'wolkjes-baby-blauw', 'label' => 'Wolkjes baby-blauw', 'beschrijving' => 'Zachte wolkjes en sterretjes in baby-blauw, rustig en lief'],
            ['slug' => 'wolkjes-baby-roze', 'label' => 'Wolkjes baby-roze', 'beschrijving' => 'Zachte wolkjes en sterretjes in baby-roze, rustig en lief'],
            ['slug' => 'aquarel-beertjes', 'label' => 'Aquarel beertjes-thema', 'beschrijving' => 'Subtiele aquarel-wolkjes met kleine sterretjes in mintgroen en crème'],
            ['slug' => 'zachte-regenboog-baby', 'label' => 'Zachte regenboog', 'beschrijving' => 'Heel zachte pastel regenboogtinten, luchtig en vredig'],
            ['slug' => 'maan-en-sterren', 'label' => 'Maan & sterren', 'beschrijving' => 'Dromerige maan- en sterren-motieven in zacht lavendel en crème'],
            ['slug' => 'babyshower-groen-neutraal', 'label' => 'Salie groen neutraal', 'beschrijving' => 'Zachte salie-groene tinten, gender-neutraal en rustig'],
            ['slug' => 'babyshower-geel-neutraal', 'label' => 'Zacht geel neutraal', 'beschrijving' => 'Zacht zonnig geel met kleine wolkjes, vrolijk en neutraal'],
            ['slug' => 'eendjes-zacht-geel', 'label' => 'Eendjes zacht geel', 'beschrijving' => 'Zachte badeendjes-motief in zacht geel en wit, lief en speels'],
            ['slug' => 'sterretjes-lavendel', 'label' => 'Sterretjes lavendel', 'beschrijving' => 'Kleine sterretjes-motief in zacht lavendel, rustig en dromerig'],
            ['slug' => 'konijntjes-pastel', 'label' => 'Konijntjes pastel', 'beschrijving' => 'Subtiele konijntjes-silhouetten in zacht pastelroze'],
            ['slug' => 'wolk-en-regenboog-zacht', 'label' => 'Wolk en regenboog', 'beschrijving' => 'Zachte wolk- en regenboogmotief in pastel, vredig'],
            ['slug' => 'babyvoetjes-motief', 'label' => 'Babyvoetjes motief', 'beschrijving' => 'Speelse kleine babyvoetjes-motief in zacht mintgroen'],
            ['slug' => 'sterrenhemel-baby', 'label' => 'Sterrenhemel baby', 'beschrijving' => 'Dromerige sterrenhemel in zacht marineblauw en goud, rustig'],
            ['slug' => 'teddybeer-silhouet', 'label' => 'Teddybeer silhouet', 'beschrijving' => 'Zachte teddybeer-silhouetten in crème en beige'],
            ['slug' => 'bloemetjes-baby-zacht', 'label' => 'Bloemetjes zacht', 'beschrijving' => 'Kleine zachte bloemetjes-motief in pastel perzik'],
            ['slug' => 'mobiel-motief-lief', 'label' => 'Mobile motief', 'beschrijving' => 'Speels mobile-slinger-motief met sterren en manen, zacht pastel'],
            ['slug' => 'giraffe-silhouet-zacht', 'label' => 'Giraffe silhouet', 'beschrijving' => 'Zachte giraffe-silhouetten in pastel geel en beige, lief'],
            ['slug' => 'initialen-ballon-neutraal', 'label' => 'Ballon neutraal', 'beschrijving' => 'Speelse ballon-motief in zacht neutraal mint en perzik'],
            ['slug' => 'wolkjes-en-manen-zacht', 'label' => 'Wolkjes en manen', 'beschrijving' => 'Zachte wolkjes- en manenmotief in poederblauw'],
            ['slug' => 'sterrenstof-baby-zacht', 'label' => 'Sterrenstof zacht', 'beschrijving' => 'Dromerige sterrenstof-motief in zacht poederroze en lavendel'],
        ],
        'lente-bloesem' => [
            ['slug' => 'kersenbloesem-roze', 'label' => 'Kersenbloesem roze', 'beschrijving' => 'Zachte kersenbloesem-takken in roze en wit, luchtig lentegevoel'],
            ['slug' => 'tulpen-aquarel', 'label' => 'Tulpen aquarel', 'beschrijving' => 'Losse aquarel tulpen in pastel geel en roze'],
            ['slug' => 'narcissen-fris', 'label' => 'Narcissen fris', 'beschrijving' => 'Frisse narcissen-motieven in geel en groen, vrolijk voorjaar'],
            ['slug' => 'lentegroen-waas', 'label' => 'Lentegroen waas', 'beschrijving' => 'Zachte lentegroene waas met kleine bloesemblaadjes'],
            ['slug' => 'paasei-pastel', 'label' => 'Paasei pastel', 'beschrijving' => 'Zachte pastel paastinten met kleine bloemetjes, vrolijk lentegevoel'],
            ['slug' => 'vlinders-lente-zacht', 'label' => 'Vlinders lente', 'beschrijving' => 'Zachte vlinder-silhouetten tussen bloesems, luchtig lentegevoel'],
            ['slug' => 'lieveheersbeestjes-fris', 'label' => 'Lieveheersbeestjes fris', 'beschrijving' => 'Speelse lieveheersbeestjes-motief in fris groen en rood'],
            ['slug' => 'viooltjes-lente-paars', 'label' => 'Viooltjes lente paars', 'beschrijving' => 'Kleine viooltjes in zacht paars en geel, vrolijk voorjaar'],
            ['slug' => 'madeliefjes-wei', 'label' => 'Madeliefjes wei', 'beschrijving' => 'Losse madeliefjes-motief in een zachte groene wei'],
            ['slug' => 'bijen-en-bloesem', 'label' => 'Bijen en bloesem', 'beschrijving' => 'Zachte bijen- en bloesemmotief in geel en wit'],
            ['slug' => 'regenboog-lente-zacht', 'label' => 'Regenboog lente', 'beschrijving' => 'Zachte lente-regenboog na de regen, fris en luchtig'],
            ['slug' => 'paaslelie-wit-geel', 'label' => 'Paaslelie wit-geel', 'beschrijving' => 'Elegante paaslelie-motief in wit en zacht geel'],
            ['slug' => 'lentewind-waas', 'label' => 'Lentewind waas', 'beschrijving' => 'Zachte waas geïnspireerd op een lentebriesje, pastelgroen en roze'],
            ['slug' => 'hyacinten-paars-blauw', 'label' => 'Hyacinten paars-blauw', 'beschrijving' => 'Zachte hyacinten in paars en blauw, geurig lentegevoel'],
            ['slug' => 'eendjes-lentevijver', 'label' => 'Eendjes lentevijver', 'beschrijving' => 'Speelse eendjes bij een lentevijver, zacht blauw en groen'],
            ['slug' => 'kruidentuin-fris-groen', 'label' => 'Kruidentuin fris groen', 'beschrijving' => 'Frisse kruidentuin-geïnspireerde groentinten'],
            ['slug' => 'lente-regenbui-zacht', 'label' => 'Lente regenbui', 'beschrijving' => 'Zachte regendruppel-motief met een vleugje zon, fris'],
            ['slug' => 'bloesemtak-roze-wit', 'label' => 'Bloesemtak roze-wit', 'beschrijving' => 'Losse bloesemtakken in roze en wit tegen een lichte lucht'],
            ['slug' => 'gras-en-boterbloemen', 'label' => 'Gras en boterbloemen', 'beschrijving' => 'Zachte boterbloemen-motief in een fris groen grasveld'],
            ['slug' => 'appelbloesem-zacht', 'label' => 'Appelbloesem zacht', 'beschrijving' => 'Zachte appelbloesem-takken in wit en roze, fris lentegevoel'],
            ['slug' => 'libelle-lentevijver', 'label' => 'Libelle lentevijver', 'beschrijving' => 'Speelse libelle-silhouetten bij een lentevijver, zacht blauwgroen'],
            ['slug' => 'paddenstoeltjes-lente-fris', 'label' => 'Paddenstoeltjes lente', 'beschrijving' => 'Speelse kleine paddenstoeltjes in fris lentegroen'],
            ['slug' => 'narcissenveld-geel', 'label' => 'Narcissenveld geel', 'beschrijving' => 'Vrolijk narcissenveld in zacht geel en groen'],
            ['slug' => 'lentebries-linten', 'label' => 'Lentebries linten', 'beschrijving' => 'Speelse zachte lint-motieven wapperend in de lentebries'],
            ['slug' => 'kruidentuin-bloesem', 'label' => 'Kruidentuin bloesem', 'beschrijving' => 'Zachte combinatie van kruiden en lentebloesem, fris'],
            ['slug' => 'eerste-lentebloemen', 'label' => 'Eerste lentebloemen', 'beschrijving' => 'Tere eerste lentebloempjes in zacht wit en geel'],
            ['slug' => 'zonnestraal-lente-fris', 'label' => 'Zonnestraal lente', 'beschrijving' => 'Frisse zonnestraal-gloed door jong lentegroen'],
            ['slug' => 'lentetuin-aquarel-zacht', 'label' => 'Lentetuin aquarel', 'beschrijving' => 'Zachte aquarel lentetuin-waas in diverse pastelkleuren'],
            ['slug' => 'krokussen-paars-geel', 'label' => 'Krokussen paars-geel', 'beschrijving' => 'Vrolijke krokussen in paars en geel, vroeg lentegevoel'],
            ['slug' => 'lente-vogeltjes-zacht', 'label' => 'Lente vogeltjes', 'beschrijving' => 'Speelse kleine vogeltjes tussen lentetakken, zacht en luchtig'],
            ['slug' => 'bloesemblaadjes-wind', 'label' => 'Bloesemblaadjes wind', 'beschrijving' => 'Dwarrelende bloesemblaadjes in de lentewind'],
            ['slug' => 'lentetak-knoppen', 'label' => 'Lentetak knoppen', 'beschrijving' => 'Zachte lentetakken met prille knoppen, fris en teder'],
            ['slug' => 'paashaas-silhouet-zacht', 'label' => 'Paashaas silhouet', 'beschrijving' => 'Speels paashaas-silhouet tussen lentebloemen, zacht pastel'],
            ['slug' => 'ochtenddauw-lente', 'label' => 'Ochtenddauw lente', 'beschrijving' => 'Zachte ochtenddauw-glinster op jong lentegroen'],
        ],
        'herfst-warm' => [
            ['slug' => 'herfstbladeren-terracotta', 'label' => 'Herfstbladeren terracotta', 'beschrijving' => 'Vallende herfstbladeren in terracotta en oker'],
            ['slug' => 'pompoen-warm-oranje', 'label' => 'Warm oranje', 'beschrijving' => 'Warme oranje en bruine herfsttinten, gezellig'],
            ['slug' => 'eikenblad-goud', 'label' => 'Eikenblad goud', 'beschrijving' => 'Gouden eikenbladeren-motief op een zachte crème ondergrond'],
            ['slug' => 'herfstboom-silhouet', 'label' => 'Herfstboom silhouet', 'beschrijving' => 'Zachte silhouetten van herfstbomen aan de randen'],
            ['slug' => 'kastanjebruin-amber', 'label' => 'Kastanjebruin amber', 'beschrijving' => 'Warme kastanjebruine en amberkleurige waas, herfstig en knus'],
            ['slug' => 'paddenstoelen-herfst', 'label' => 'Paddenstoelen herfst', 'beschrijving' => 'Speelse paddenstoelen-silhouetten in warme herfsttinten'],
            ['slug' => 'herfstwind-bladeren', 'label' => 'Herfstwind bladeren', 'beschrijving' => 'Dwarrelende herfstbladeren in een zachte windwaas'],
            ['slug' => 'kaneelstokjes-warm', 'label' => 'Kaneelstokjes warm', 'beschrijving' => 'Warme kaneelstokjes-geïnspireerde bruintinten, gezellig'],
            ['slug' => 'bospaden-herfst', 'label' => 'Bospaden herfst', 'beschrijving' => 'Zachte silhouetten van een bospad in herfstkleuren'],
            ['slug' => 'hertenmotief-herfst', 'label' => 'Hertenmotief herfst', 'beschrijving' => 'Subtiel hertensilhouet tussen herfstbladeren, warm bruin'],
            ['slug' => 'acorn-eikel-motief', 'label' => 'Eikel motief', 'beschrijving' => 'Speels eikel- en kastanje-motief in bruin en goud'],
            ['slug' => 'herfstmist-amber', 'label' => 'Herfstmist amber', 'beschrijving' => 'Zachte ochtendmist-waas in amber en terracotta'],
            ['slug' => 'wijnrood-blad-herfst', 'label' => 'Wijnrood blad herfst', 'beschrijving' => 'Wijnrode herfstbladeren-motief, rijk en warm'],
            ['slug' => 'oogst-warm-goud', 'label' => 'Oogst warm goud', 'beschrijving' => 'Warme oogst-geïnspireerde goudtinten, gezellig herfstgevoel'],
            ['slug' => 'dennenappel-herfst', 'label' => 'Dennenappel herfst', 'beschrijving' => 'Speelse dennenappel-silhouetten in bruin en groen'],
            ['slug' => 'herfstzon-gloed', 'label' => 'Herfstzon gloed', 'beschrijving' => 'Zachte lage herfstzon-gloed in oranje en amber'],
            ['slug' => 'mosgroen-herfst-warm', 'label' => 'Mosgroen herfst', 'beschrijving' => 'Warme mosgroene tinten gecombineerd met terracotta'],
            ['slug' => 'sinaasappel-kaneel-warm', 'label' => 'Sinaasappel kaneel', 'beschrijving' => 'Warme sinaasappel- en kaneeltinten, gezellig herfstig'],
            ['slug' => 'amber-blad-waterverf', 'label' => 'Amber blad waterverf', 'beschrijving' => 'Zachte waterverf herfstbladeren in amber en oker'],
            ['slug' => 'herfstweide-goud', 'label' => 'Herfstweide goud', 'beschrijving' => 'Warme goudkleurige herfstweide-waas, rustig'],
            ['slug' => 'notenmotief-herfst', 'label' => 'Notenmotief herfst', 'beschrijving' => 'Speels motief van walnoten en hazelnoten in bruintinten'],
            ['slug' => 'herfstavond-gloed', 'label' => 'Herfstavond gloed', 'beschrijving' => 'Zachte warme avondgloed in diep oranje en bruin'],
            ['slug' => 'druiventros-herfst', 'label' => 'Druiventros herfst', 'beschrijving' => 'Rijke druiventros-silhouetten in bordeaux en groen'],
            ['slug' => 'sjaal-textuur-warm', 'label' => 'Sjaal textuur warm', 'beschrijving' => 'Zachte gebreide sjaal-geïnspireerde textuur in warme herfsttinten'],
            ['slug' => 'late-zonnestralen-herfst', 'label' => 'Late zonnestralen', 'beschrijving' => 'Zachte lage zonnestralen door herfstbladeren, warm en dromerig'],
            ['slug' => 'karamel-goud-herfst', 'label' => 'Karamel goud herfst', 'beschrijving' => 'Warme karamel- en goudkleurige tinten, gezellig herfstgevoel'],
            ['slug' => 'herfstblad-waterval-warm', 'label' => 'Herfstblad waterval', 'beschrijving' => 'Zachte waterval van vallende herfstbladeren, warm en dromerig'],
            ['slug' => 'cognac-bruin-herfst', 'label' => 'Cognac bruin herfst', 'beschrijving' => 'Warme cognacbruine tinten, rijk en gezellig herfstig'],
            ['slug' => 'herfstochtend-goud', 'label' => 'Herfstochtend goud', 'beschrijving' => 'Zachte gouden herfstochtend-gloed, rustig en warm'],
        ],
        'kleurrijk-vrolijk' => [
            ['slug' => 'regenboog-confetti', 'label' => 'Regenboog confetti', 'beschrijving' => 'Vrolijke regenboogkleurige confetti, speels en fris'],
            ['slug' => 'neon-vrolijk', 'label' => 'Neon vrolijk', 'beschrijving' => 'Felle vrolijke neonkleuren in een zachte gradiënt'],
            ['slug' => 'gekleurde-stippen', 'label' => 'Gekleurde stippen', 'beschrijving' => 'Speels gekleurd stippen-motief in vrolijke kleuren'],
            ['slug' => 'kleurrijke-cirkels', 'label' => 'Kleurrijke cirkels', 'beschrijving' => 'Zachte overlappende cirkels in vrolijke kleuren'],
            ['slug' => 'regenboog-waaier', 'label' => 'Regenboog waaier', 'beschrijving' => 'Speelse regenboog-waaiervorm aan de randen, vrolijk en fris'],
            ['slug' => 'kleurrijke-strepen-vrolijk', 'label' => 'Kleurrijke strepen', 'beschrijving' => 'Speelse verticale kleurrijke strepen, fris en vrolijk'],
            ['slug' => 'confetti-explosie-kleur', 'label' => 'Confetti explosie', 'beschrijving' => 'Vrolijke confetti-explosie in felle regenboogkleuren'],
            ['slug' => 'kleurblokken-zacht', 'label' => 'Kleurblokken zacht', 'beschrijving' => 'Zachte overlappende kleurblokken in vrolijke tinten, vloeiend in elkaar overlopend'],
            ['slug' => 'regenboog-golven-vrolijk', 'label' => 'Regenboog golven', 'beschrijving' => 'Speelse golvende regenboogkleuren-patroon'],
            ['slug' => 'vrolijke-ballonnen-kleur', 'label' => 'Vrolijke ballonnen', 'beschrijving' => 'Kleurrijke ballonnen-silhouetten, feestelijk vrolijk'],
            ['slug' => 'kleurrijke-vlekken-aquarel', 'label' => 'Kleurrijke vlekken', 'beschrijving' => 'Losse vrolijke aquarel-kleurvlekken'],
            ['slug' => 'regenboog-hart-motief', 'label' => 'Regenboog hart', 'beschrijving' => 'Speels regenboog-hart-motief, vrolijk en fris'],
            ['slug' => 'kleurrijke-veren-motief', 'label' => 'Kleurrijke veren', 'beschrijving' => 'Vrolijke kleurrijke veren-silhouetten, speels en licht'],
            ['slug' => 'vrolijke-zigzag-kleur', 'label' => 'Vrolijke zigzag', 'beschrijving' => 'Speels zigzag-patroon in vrolijke kleuren'],
            ['slug' => 'kleurrijke-vlinders', 'label' => 'Kleurrijke vlinders', 'beschrijving' => 'Vrolijke kleurrijke vlinder-silhouetten'],
            ['slug' => 'regenboog-spiraal-vrolijk', 'label' => 'Regenboog spiraal', 'beschrijving' => 'Speelse regenboogkleurige spiraalvormen'],
            ['slug' => 'kleurrijke-sterren-vrolijk', 'label' => 'Kleurrijke sterren', 'beschrijving' => 'Vrolijke kleurrijke sterren-motief tegen een lichte achtergrond'],
            ['slug' => 'prisma-kleur-verloop', 'label' => 'Prisma kleurverloop', 'beschrijving' => 'Speels prisma-geïnspireerd kleurverloop, fris en levendig'],
            ['slug' => 'vrolijke-taartjes-kleur', 'label' => 'Vrolijke taartjes', 'beschrijving' => 'Speelse kleurrijke taartjes-motief, vrolijk en zoet'],
            ['slug' => 'kleurrijke-ijsjes-motief', 'label' => 'Kleurrijke ijsjes', 'beschrijving' => 'Vrolijk kleurrijk ijsjes-motief, speels en fris'],
            ['slug' => 'regenboog-veren-licht', 'label' => 'Regenboog veren', 'beschrijving' => 'Lichte regenboogkleurige veren-silhouetten'],
            ['slug' => 'kleurrijke-vissen-motief', 'label' => 'Kleurrijke vissen', 'beschrijving' => 'Speelse kleurrijke visjes-motief, vrolijk aquatisch'],
            ['slug' => 'vrolijke-vogels-kleur', 'label' => 'Vrolijke vogels', 'beschrijving' => 'Kleurrijke speelse vogel-silhouetten'],
            ['slug' => 'kleurrijke-planeten-motief', 'label' => 'Kleurrijke planeten', 'beschrijving' => 'Speels kleurrijk planeten- en sterren-motief'],
            ['slug' => 'regenboog-vlinders-vrolijk', 'label' => 'Regenboog vlinders', 'beschrijving' => 'Vrolijke regenboogkleurige vlinder-silhouetten'],
            ['slug' => 'kleurrijke-cupcakes-motief', 'label' => 'Kleurrijke cupcakes', 'beschrijving' => 'Speelse kleurrijke cupcakes-motief, zoet en vrolijk'],
            ['slug' => 'vrolijke-diamantjes-kleur', 'label' => 'Vrolijke diamantjes', 'beschrijving' => 'Kleurrijke speelse diamant-vormen, sprankelend'],
            ['slug' => 'regenboog-druppels-vrolijk', 'label' => 'Regenboog druppels', 'beschrijving' => 'Vrolijke regenboogkleurige waterdruppel-motief'],
            ['slug' => 'kleurrijke-slingers-vrolijk', 'label' => 'Kleurrijke slingers', 'beschrijving' => 'Speelse kleurrijke slingers-motief, feestelijk vrolijk'],
            ['slug' => 'regenboog-confetti-licht', 'label' => 'Regenboog confetti licht', 'beschrijving' => 'Lichte regenboogkleurige confetti-spikkels, subtiel vrolijk'],
            ['slug' => 'kleurrijke-parasol-motief', 'label' => 'Kleurrijke parasol', 'beschrijving' => 'Vrolijke kleurrijke parasol-silhouetten'],
            ['slug' => 'vrolijke-kleurspatten-fris', 'label' => 'Vrolijke kleurspatten', 'beschrijving' => 'Frisse vrolijke kleurspatten-motief'],
            ['slug' => 'kleurrijke-linten-zwevend', 'label' => 'Kleurrijke linten zwevend', 'beschrijving' => 'Zwevende kleurrijke lintjes, speels en licht'],
        ],
        'pastel-zacht' => [
            ['slug' => 'pastel-wolken', 'label' => 'Pastel wolken', 'beschrijving' => 'Zachte pastelkleurige wolkenlucht, rustig en dromerig'],
            ['slug' => 'pastel-regenboog-zacht', 'label' => 'Pastel regenboog', 'beschrijving' => 'Heel zachte pastel regenboogtinten in een vloeiende gradiënt'],
            ['slug' => 'pastel-marmer', 'label' => 'Pastel marmer', 'beschrijving' => 'Zacht pastel marmer-effect in lila en mint'],
            ['slug' => 'pastel-aquarel-waas', 'label' => 'Pastel aquarel waas', 'beschrijving' => 'Losse pastel aquarel-vlekken, licht en luchtig'],
            ['slug' => 'pastel-confetti-zacht', 'label' => 'Pastel confetti', 'beschrijving' => 'Subtiele pastelkleurige confetti, zacht en elegant'],
            ['slug' => 'pastel-strepen-zacht', 'label' => 'Pastel strepen', 'beschrijving' => 'Zachte pastelkleurige verticale strepen, rustig en licht'],
            ['slug' => 'pastel-bloesem-waas', 'label' => 'Pastel bloesem waas', 'beschrijving' => 'Losse pastel bloesemtakken in een zachte waas'],
            ['slug' => 'pastel-sterren-licht', 'label' => 'Pastel sterren', 'beschrijving' => 'Zachte pastelkleurige sterren-motief, dromerig'],
            ['slug' => 'pastel-golven-zacht', 'label' => 'Pastel golven', 'beschrijving' => 'Kalme pastelkleurige golvenpatroon, rustgevend'],
            ['slug' => 'pastel-cirkels-licht', 'label' => 'Pastel cirkels', 'beschrijving' => 'Zachte overlappende pastelkleurige cirkels'],
            ['slug' => 'pastel-veren-licht', 'label' => 'Pastel veren', 'beschrijving' => 'Lichte pastelkleurige veren-silhouetten, luchtig'],
            ['slug' => 'pastel-hart-motief', 'label' => 'Pastel hart motief', 'beschrijving' => 'Zacht pastel hart-motief, subtiel en lief'],
            ['slug' => 'pastel-mist-waas', 'label' => 'Pastel mist waas', 'beschrijving' => 'Zachte pastelkleurige mistige waas, dromerig'],
            ['slug' => 'pastel-vlinders-licht', 'label' => 'Pastel vlinders', 'beschrijving' => 'Lichte pastelkleurige vlinder-silhouetten'],
            ['slug' => 'pastel-spikkels-zacht', 'label' => 'Pastel spikkels', 'beschrijving' => 'Fijne pastelkleurige spikkels, subtiel en licht'],
            ['slug' => 'pastel-maan-sterren', 'label' => 'Pastel maan en sterren', 'beschrijving' => 'Dromerige pastel maan- en sterrenmotief'],
            ['slug' => 'pastel-lint-zacht', 'label' => 'Pastel lint', 'beschrijving' => 'Speels zacht pastelkleurig lint-motief'],
            ['slug' => 'pastel-schelpen-zacht', 'label' => 'Pastel schelpen', 'beschrijving' => 'Zachte pastelkleurige schelpenmotief, rustig'],
            ['slug' => 'pastel-manen-fase', 'label' => 'Pastel maanfasen', 'beschrijving' => 'Dromerige pastelkleurige maanfasen-motief'],
            ['slug' => 'pastel-druppels-licht', 'label' => 'Pastel druppels', 'beschrijving' => 'Lichte pastelkleurige waterdruppel-motief'],
            ['slug' => 'pastel-linten-zweven', 'label' => 'Pastel linten zweven', 'beschrijving' => 'Zwevende pastelkleurige lintjes, luchtig'],
            ['slug' => 'pastel-bessen-zacht', 'label' => 'Pastel bessen', 'beschrijving' => 'Zachte pastelkleurige bessenmotief'],
            ['slug' => 'pastel-diamant-licht', 'label' => 'Pastel diamant', 'beschrijving' => 'Lichte pastelkleurige diamantvormen, subtiel sprankelend'],
            ['slug' => 'pastel-zonsopgang-zacht', 'label' => 'Pastel zonsopgang', 'beschrijving' => 'Zachte pastelkleurige zonsopgang-gradiënt, rustig'],
            ['slug' => 'pastel-luchtballon-zacht', 'label' => 'Pastel luchtballon', 'beschrijving' => 'Zachte pastelkleurige luchtballon-silhouetten, dromerig'],
            ['slug' => 'pastel-parelmoer-glans', 'label' => 'Pastel parelmoer glans', 'beschrijving' => 'Subtiele pastelkleurige parelmoer-glans, elegant licht'],
            ['slug' => 'pastel-zeepbellen-licht', 'label' => 'Pastel zeepbellen', 'beschrijving' => 'Lichte pastelkleurige zeepbellen-motief, luchtig'],
        ],
        'natuur-botanisch' => [
            ['slug' => 'varens-groen', 'label' => 'Varens groen', 'beschrijving' => 'Zachte varenbladeren in verschillende groentinten'],
            ['slug' => 'botanische-tak', 'label' => 'Botanische tak', 'beschrijving' => 'Losse botanische takjes en bladeren, natuurlijk en rustig'],
            ['slug' => 'wilde-planten', 'label' => 'Wilde planten', 'beschrijving' => 'Wilde plantensilhouetten in zachte aquarelkleuren'],
            ['slug' => 'bladgroen-waas', 'label' => 'Bladgroen waas', 'beschrijving' => 'Zachte groene bladeren-waas, fris en natuurlijk'],
            ['slug' => 'monstera-blad', 'label' => 'Monstera blad', 'beschrijving' => 'Grote monstera-bladeren silhouet in fris groen, botanisch en modern'],
            ['slug' => 'bamboe-silhouet-groen', 'label' => 'Bamboe silhouet', 'beschrijving' => 'Zachte bamboe-silhouetten in fris groen'],
            ['slug' => 'bloemenveld-botanisch', 'label' => 'Bloemenveld botanisch', 'beschrijving' => 'Losse botanische bloemenveld-waas in aardse tinten'],
            ['slug' => 'mos-en-korstmos', 'label' => 'Mos en korstmos', 'beschrijving' => 'Zachte mos- en korstmos-textuur in groentinten'],
            ['slug' => 'palmblad-botanisch-groen', 'label' => 'Palmblad botanisch', 'beschrijving' => 'Grote palmbladeren-silhouet in natuurlijk groen'],
            ['slug' => 'wilg-takken-zacht', 'label' => 'Wilgentakken zacht', 'beschrijving' => 'Zachte wilgentakken-motief, natuurlijk en rustig'],
            ['slug' => 'kruiden-botanisch-groen', 'label' => 'Kruiden botanisch', 'beschrijving' => 'Botanische kruiden-illustratie in zachtgroen'],
            ['slug' => 'lotus-blad-botanisch', 'label' => 'Lotusblad botanisch', 'beschrijving' => 'Elegante lotusbladeren-silhouet in zachtgroen en wit'],
            ['slug' => 'wijnrank-botanisch', 'label' => 'Wijnrank botanisch', 'beschrijving' => 'Losse wijnrank-motief in groen en bordeaux'],
            ['slug' => 'gras-silhouet-natuurlijk', 'label' => 'Gras silhouet', 'beschrijving' => 'Zachte grassprieten-silhouet, natuurlijk en luchtig'],
            ['slug' => 'veren-en-blad-natuurlijk', 'label' => 'Veren en blad', 'beschrijving' => 'Natuurlijke combinatie van veren en bladeren, aards'],
            ['slug' => 'bostextuur-groen-zacht', 'label' => 'Bostextuur groen', 'beschrijving' => 'Zachte bostextuur-waas in diverse groentinten'],
            ['slug' => 'paddenstoel-botanisch-natuurlijk', 'label' => 'Paddenstoel botanisch', 'beschrijving' => 'Botanische paddenstoel-illustratie in natuurlijke tinten'],
            ['slug' => 'dennennaalden-groen', 'label' => 'Dennennaalden groen', 'beschrijving' => 'Zachte dennennaalden-textuur in diep groen'],
            ['slug' => 'paddenstoelenring-natuurlijk', 'label' => 'Paddenstoelenring', 'beschrijving' => 'Natuurlijke paddenstoelenring-motief in aardse tinten'],
            ['slug' => 'rivierstenen-zacht', 'label' => 'Rivierstenen zacht', 'beschrijving' => 'Zachte rivierstenen-silhouetten in grijsgroen'],
            ['slug' => 'blad-nerven-macro', 'label' => 'Bladnerven macro', 'beschrijving' => 'Fijne bladnerven-motief in macrostijl, natuurlijk groen'],
            ['slug' => 'korenveld-botanisch', 'label' => 'Korenveld botanisch', 'beschrijving' => 'Zacht korenveld-motief in warm goudgroen'],
            ['slug' => 'zeewier-botanisch-groen', 'label' => 'Zeewier botanisch', 'beschrijving' => 'Natuurlijk zeewier-silhouet in diep groen'],
            ['slug' => 'varenbos-schaduw-groen', 'label' => 'Varenbos schaduw', 'beschrijving' => 'Zachte varenbos-schaduwen in diepgroen'],
            ['slug' => 'bosviooltjes-botanisch', 'label' => 'Bosviooltjes botanisch', 'beschrijving' => 'Kleine bosviooltjes tussen botanisch groen, natuurlijk en rustig'],
            ['slug' => 'druppels-op-blad-natuurlijk', 'label' => 'Druppels op blad', 'beschrijving' => 'Zachte dauwdruppels op bladeren, fris en natuurlijk'],
            ['slug' => 'wilgenkatjes-botanisch', 'label' => 'Wilgenkatjes botanisch', 'beschrijving' => 'Zachte wilgenkatjes-motief, natuurlijk vroeg voorjaar'],
        ],
    ];

    public function handle(): int
    {
        $only = $this->option('only');
        $start = (int) $this->option('start');
        $slug = $this->option('slug');
        $fromIndex = (int) $this->option('from-index');
        $manager = app(ImageGenerationManager::class);

        $counter = 0;
        $failures = [];

        foreach (self::CATEGORIES as $category => $themes) {
            if ($only && $category !== $only) {
                continue;
            }

            foreach ($themes as $i => $theme) {
                $counter++;
                if ($slug && $theme['slug'] !== $slug) {
                    continue;
                }
                if (!$slug && $counter < $start) {
                    continue;
                }
                if (!$slug && ($i + 1) < $fromIndex) {
                    continue;
                }

                $prompt = str_replace('{beschrijving}', $theme['beschrijving'], self::PROMPT_TEMPLATE);
                $this->info("[{$counter}] {$category}/{$theme['slug']} — genereren…");

                try {
                    $image = $manager->driver('gemini')->generate($prompt, []);
                    $binary = $this->coverCropToCanvas($image->binary);

                    $relativePath = "template-previews/{$category}/" . ($i + 1) . "-{$theme['slug']}.jpg";
                    Storage::disk('public')->put($relativePath, $binary);

                    $this->info("  ✓ opgeslagen: storage/app/public/{$relativePath}");
                } catch (\Throwable $e) {
                    $this->error("  ✗ mislukt: {$e->getMessage()}");
                    $failures[] = "{$category}/{$theme['slug']}";
                }
            }
        }

        if ($failures) {
            $this->warn('Mislukte templates: ' . implode(', ', $failures));
        }

        $this->info('Klaar.');

        return self::SUCCESS;
    }

    private function coverCropToCanvas(string $binary): string
    {
        $image = new Imagick();
        $image->readImageBlob($binary);
        $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        $srcWidth = $image->getImageWidth();
        $srcHeight = $image->getImageHeight();
        $scale = max(self::CANVAS_WIDTH / $srcWidth, self::CANVAS_HEIGHT / $srcHeight);

        $image->resizeImage(
            (int) ceil($srcWidth * $scale),
            (int) ceil($srcHeight * $scale),
            Imagick::FILTER_LANCZOS,
            1
        );

        $cropX = (int) round(($image->getImageWidth() - self::CANVAS_WIDTH) / 2);
        $cropY = (int) round(($image->getImageHeight() - self::CANVAS_HEIGHT) / 2);
        $image->cropImage(self::CANVAS_WIDTH, self::CANVAS_HEIGHT, max(0, $cropX), max(0, $cropY));
        $image->setImagePage(0, 0, 0, 0);

        $image->setImageFormat('jpg');
        $image->setImageCompressionQuality(90);

        return $image->getImageBlob();
    }
}
