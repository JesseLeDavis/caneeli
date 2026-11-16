<?php
/**
 * Draws stand-in product photos as SVG for the test environment: a piece on a
 * wooden table against a wall. Glass pieces are cut into panes with dark
 * lead lines; ceramics get a glaze with drips and speckle. Deterministic per
 * seed, so a reset produces the same shop.
 */

const GLASS = [
    'sunset' => ['#E8743B', '#F2B84B', '#C25B32', '#F6D78B', '#D9472B'],
    'pond'   => ['#3E8E9C', '#6497D6', '#9CC9D9', '#2F6F7A', '#C1E1FA'],
    'meadow' => ['#7BA24A', '#D4C14B', '#A9C76B', '#4F7A3A', '#EDE3A0'],
    'berry'  => ['#8E3B5E', '#C25B32', '#E6A0B4', '#5C2D4F', '#F2C6C2'],
    'clear'  => ['#E9EEF0', '#CFE0E6', '#F6F4EE', '#B9CDD6', '#DDE7EA'],
];

const GLAZE = [
    'oatmeal' => ['#E8DCC4', '#C9B48F', '#8C6F4E'],
    'celadon' => ['#B8CDB4', '#8FAE95', '#5E7E68'],
    'rust'    => ['#D07A4E', '#A84B27', '#6E2F17'],
    'ink'     => ['#4A5160', '#2F3540', '#9AA3B0'],
    'butter'  => ['#F0D98A', '#D4B44B', '#9B7C2A'],
];

final class Rand {
    private int $s;
    public function __construct(int $seed) { $this->s = $seed ?: 1; }
    public function f(): float { $this->s = ($this->s * 1103515245 + 12345) & 0x7fffffff; return $this->s / 0x7fffffff; }
    public function range(float $a, float $b): float { return $a + ($b - $a) * $this->f(); }
    public function pick(array $a) { return $a[(int) floor($this->f() * count($a)) % count($a)]; }
}

/** Wall, table and soft light — shared by every "photo". */
function scene_open(Rand $r, string $wall, string $wood): string {
    $w = 1200; $h = 1500;
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 $w $h" width="$w" height="$h">
<defs>
  <linearGradient id="wall" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="$wall"/><stop offset="1" stop-color="$wall" stop-opacity=".82"/>
  </linearGradient>
  <linearGradient id="table" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="$wood"/><stop offset="1" stop-color="#5A3218"/>
  </linearGradient>
  <radialGradient id="light" cx=".7" cy=".2" r=".9">
    <stop offset="0" stop-color="#fff" stop-opacity=".35"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
  </radialGradient>
  <radialGradient id="shadow" cx=".5" cy=".5" r=".5">
    <stop offset="0" stop-color="#000" stop-opacity=".35"/><stop offset="1" stop-color="#000" stop-opacity="0"/>
  </radialGradient>
</defs>
<rect width="$w" height="$h" fill="url(#wall)"/>
<rect y="1060" width="$w" height="440" fill="url(#table)"/>
<g stroke="#000" stroke-opacity=".07" stroke-width="3" fill="none">
  <path d="M0 1120 C300 1110 700 1135 1200 1118"/><path d="M0 1210 C400 1200 800 1228 1200 1205"/>
  <path d="M0 1330 C350 1318 750 1345 1200 1322"/><path d="M0 1440 C420 1430 760 1452 1200 1436"/>
</g>
<rect width="$w" height="$h" fill="url(#light)"/>
SVG;
}

/** Cut a region into jittered panes with lead lines, clipped to $clipId. */
function panes(Rand $r, array $box, array $colors, string $clipId, int $cols = 4, int $rows = 5): string {
    [$x0, $y0, $x1, $y1] = $box;
    $pts = [];
    for ($j = 0; $j <= $rows; $j++) {
        for ($i = 0; $i <= $cols; $i++) {
            $edge = $i === 0 || $j === 0 || $i === $cols || $j === $rows;
            $px = $x0 + ($x1 - $x0) * $i / $cols + ($edge ? 0 : $r->range(-0.28, 0.28) * ($x1 - $x0) / $cols);
            $py = $y0 + ($y1 - $y0) * $j / $rows + ($edge ? 0 : $r->range(-0.28, 0.28) * ($y1 - $y0) / $rows);
            $pts[$j][$i] = [round($px), round($py)];
        }
    }
    $out = "<g clip-path=\"url(#$clipId)\">";
    for ($j = 0; $j < $rows; $j++) {
        for ($i = 0; $i < $cols; $i++) {
            $a = $pts[$j][$i]; $b = $pts[$j][$i + 1]; $c = $pts[$j + 1][$i + 1]; $d = $pts[$j + 1][$i];
            // Split each cell into two triangles along a random diagonal.
            $tris = $r->f() < 0.5 ? [[$a, $b, $c], [$a, $c, $d]] : [[$a, $b, $d], [$b, $c, $d]];
            foreach ($tris as $t) {
                $fill = $r->pick($colors);
                $op = round($r->range(0.82, 0.97), 2);
                $p = implode(' ', array_map(fn($q) => $q[0] . ',' . $q[1], $t));
                $out .= "<polygon points=\"$p\" fill=\"$fill\" fill-opacity=\"$op\" stroke=\"#3A3530\" stroke-width=\"7\" stroke-linejoin=\"round\"/>";
            }
        }
    }
    // Light catching the glass.
    $out .= "<rect x=\"$x0\" y=\"$y0\" width=\"" . ($x1 - $x0) . "\" height=\"" . ($y1 - $y0) . "\" fill=\"url(#light)\"/>";
    return $out . '</g>';
}

function glass_svg(string $form, int $seed, string $paletteName): string {
    $r = new Rand($seed);
    $colors = GLASS[$paletteName];
    $svg = scene_open($r, $r->pick(['#E7DCC8', '#DCD3C2', '#E4E1D8', '#D9CDB8']), $r->pick(['#A0612E', '#8A5428', '#B5733C']));

    switch ($form) {
        case 'lamp': // glass cube on a wooden base, like the fish lamp
            $svg .= '<ellipse cx="600" cy="1110" rx="330" ry="40" fill="url(#shadow)"/>';
            $svg .= '<rect x="380" y="900" width="440" height="200" rx="10" fill="#C08A55" stroke="#6B4423" stroke-width="4"/>';
            $svg .= '<path d="M380 960 H820 M380 1030 H820" stroke="#6B4423" stroke-opacity=".25" stroke-width="3"/>';
            $svg .= '<clipPath id="c"><rect x="370" y="440" width="460" height="460" rx="6"/></clipPath>';
            $svg .= panes($r, [370, 440, 830, 900], $colors, 'c', 4, 4);
            $svg .= '<circle cx="600" cy="670" r="120" fill="' . $colors[1] . '" stroke="#3A3530" stroke-width="9"/>';
            $svg .= '<path d="M520 670 Q600 590 690 670 Q600 750 520 670 Z M690 670 L740 630 V710 Z" fill="' . $colors[0] . '" stroke="#3A3530" stroke-width="8" stroke-linejoin="round"/>';
            $svg .= '<rect x="370" y="440" width="460" height="460" rx="6" fill="none" stroke="#5B5650" stroke-width="12"/>';
            break;

        case 'suncatcher': // round panel hanging on a chain
            $svg .= '<path d="M600 0 V330" stroke="#8F8A82" stroke-width="5" stroke-dasharray="14 8"/>';
            $svg .= '<clipPath id="c"><circle cx="600" cy="640" r="300"/></clipPath>';
            $svg .= panes($r, [300, 340, 900, 940], $colors, 'c', 5, 5);
            $svg .= '<circle cx="600" cy="640" r="300" fill="none" stroke="#5B5650" stroke-width="14"/>';
            $svg .= '<ellipse cx="600" cy="1150" rx="300" ry="30" fill="' . $colors[1] . '" opacity=".25"/>';
            break;

        case 'panel': // rectangular window panel
            $svg .= '<path d="M420 0 V250 M780 0 V250" stroke="#8F8A82" stroke-width="5"/>';
            $svg .= '<clipPath id="c"><rect x="330" y="250" width="540" height="760" rx="4"/></clipPath>';
            $svg .= panes($r, [330, 250, 870, 1010], $colors, 'c', 4, 6);
            $svg .= '<rect x="330" y="250" width="540" height="760" fill="none" stroke="#5B5650" stroke-width="16"/>';
            break;

        case 'nightlight': // small arched shade
            $svg .= '<ellipse cx="600" cy="1105" rx="200" ry="26" fill="url(#shadow)"/>';
            $svg .= '<rect x="470" y="1000" width="260" height="90" rx="12" fill="#EDEBE6" stroke="#9A958B" stroke-width="4"/>';
            $svg .= '<clipPath id="c"><path d="M450 1000 V720 A150 150 0 0 1 750 720 V1000 Z"/></clipPath>';
            $svg .= panes($r, [450, 570, 750, 1000], $colors, 'c', 3, 4);
            $svg .= '<path d="M450 1000 V720 A150 150 0 0 1 750 720 V1000 Z" fill="none" stroke="#5B5650" stroke-width="12"/>';
            break;
    }
    return $svg . '</svg>';
}

function ceramic_svg(string $form, int $seed, string $glazeName): string {
    $r = new Rand($seed);
    [$base, $mid, $dark] = GLAZE[$glazeName];
    $svg = scene_open($r, $r->pick(['#E7DCC8', '#DCD3C2', '#E9E4DA']), $r->pick(['#A0612E', '#8A5428', '#B5733C']));
    $svg .= "<defs><linearGradient id=\"g\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"0\"><stop offset=\"0\" stop-color=\"$mid\"/><stop offset=\".45\" stop-color=\"$base\"/><stop offset=\"1\" stop-color=\"$dark\"/></linearGradient></defs>";

    $shape = match ($form) {
        'vase' => 'M500 420 Q470 520 420 640 Q350 820 430 1000 Q470 1080 600 1085 Q730 1080 770 1000 Q850 820 780 640 Q730 520 700 420 Z',
        'mug'  => 'M430 700 H770 V1040 Q770 1085 720 1085 H480 Q430 1085 430 1040 Z',
        'dish' => 'M300 980 Q600 900 900 980 Q860 1085 600 1090 Q340 1085 300 980 Z',
        'planter' => 'M410 640 H790 L750 1060 Q745 1085 720 1085 H480 Q455 1085 450 1060 Z',
    };
    $svg .= '<ellipse cx="600" cy="1095" rx="260" ry="34" fill="url(#shadow)"/>';
    if ($form === 'mug') {
        $svg .= "<path d=\"M770 760 Q900 760 900 880 Q900 990 770 990\" fill=\"none\" stroke=\"$mid\" stroke-width=\"42\"/>";
    }
    $svg .= "<clipPath id=\"c\"><path d=\"$shape\"/></clipPath>";
    $svg .= "<path d=\"$shape\" fill=\"url(#g)\"/>";
    // Raw clay foot, a drippy glaze line and speckle.
    $svg .= "<g clip-path=\"url(#c)\"><rect x=\"0\" y=\"1050\" width=\"1200\" height=\"60\" fill=\"#C9A57E\"/>";
    $drip = 'M0 980';
    for ($x = 0; $x <= 1200; $x += 40) {
        $drip .= ' L' . $x . ' ' . round(1000 + ($r->f() < 0.3 ? $r->range(20, 50) : $r->range(-6, 6)));
    }
    $svg .= "<path d=\"$drip V1200 H0 Z\" fill=\"$dark\" opacity=\".35\"/>";
    for ($i = 0; $i < 140; $i++) {
        $svg .= '<circle cx="' . round($r->range(250, 950)) . '" cy="' . round($r->range(400, 1090)) . '" r="' . round($r->range(1.5, 4), 1) . '" fill="#3A2A1E" opacity=".35"/>';
    }
    $svg .= '<rect x="0" y="0" width="1200" height="1500" fill="url(#light)"/></g>';
    if ($form === 'planter') {
        $svg .= '<path d="M600 640 Q560 470 470 420 M600 640 Q640 460 740 400 M600 640 Q600 480 610 360" stroke="#4F7A3A" stroke-width="16" fill="none" stroke-linecap="round"/>';
        $svg .= '<ellipse cx="470" cy="420" rx="60" ry="26" fill="#7BA24A" transform="rotate(-30 470 420)"/><ellipse cx="740" cy="400" rx="64" ry="26" fill="#7BA24A" transform="rotate(25 740 400)"/><ellipse cx="610" cy="355" rx="56" ry="24" fill="#A9C76B" transform="rotate(-80 610 355)"/>';
    }
    return $svg . '</svg>';
}

/** Ceramic lamps: a lava lamp (glazed base and cap around a glass bottle of
 *  wax) or a table lamp (thrown base under a linen shade). */
function lamp_svg(string $form, int $seed, string $glazeName, string $lava = '#E8743B'): string {
    $r = new Rand($seed);
    [$base, $mid, $dark] = GLAZE[$glazeName];
    $svg = scene_open($r, $r->pick(['#E7DCC8', '#DCD3C2', '#E9E4DA']), $r->pick(['#A0612E', '#8A5428', '#B5733C']));
    $svg .= "<defs><linearGradient id=\"g\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"0\"><stop offset=\"0\" stop-color=\"$mid\"/><stop offset=\".42\" stop-color=\"$base\"/><stop offset=\"1\" stop-color=\"$dark\"/></linearGradient>"
          . "<linearGradient id=\"liquid\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"0\"><stop offset=\"0\" stop-color=\"#FFF6D8\" stop-opacity=\".55\"/><stop offset=\".5\" stop-color=\"#FFFBEA\" stop-opacity=\".8\"/><stop offset=\"1\" stop-color=\"#F2E3B8\" stop-opacity=\".55\"/></linearGradient></defs>";
    $svg .= '<ellipse cx="600" cy="1098" rx="230" ry="30" fill="url(#shadow)"/>';

    $speckle = function (string $clip, array $box) use ($r): string {
        $out = "<g clip-path=\"url(#$clip)\">";
        for ($i = 0; $i < 90; $i++) {
            $out .= '<circle cx="' . round($r->range($box[0], $box[2])) . '" cy="' . round($r->range($box[1], $box[3])) . '" r="' . round($r->range(1.5, 3.5), 1) . '" fill="#3A2A1E" opacity=".3"/>';
        }
        return $out . '<rect width="1200" height="1500" fill="url(#light)"/></g>';
    };

    if ($form === 'lavalamp') {
        // Bottle with liquid and wax blobs
        $bottle = 'M520 420 L470 840 L730 840 L680 420 Z';
        $svg .= "<path d=\"$bottle\" fill=\"url(#liquid)\" stroke=\"#9A8F7A\" stroke-width=\"3\"/>";
        $svg .= "<clipPath id=\"b\"><path d=\"$bottle\"/></clipPath><g clip-path=\"url(#b)\">";
        $svg .= "<ellipse cx=\"600\" cy=\"835\" rx=\"140\" ry=\"40\" fill=\"$lava\"/>";
        for ($i = 0; $i < 4; $i++) {
            $cx = round($r->range(540, 660)); $cy = round($r->range(470, 760)); $rx = round($r->range(26, 46)); $ry = round($rx * $r->range(1.1, 1.6));
            $svg .= "<ellipse cx=\"$cx\" cy=\"$cy\" rx=\"$rx\" ry=\"$ry\" fill=\"$lava\"/>";
        }
        $svg .= '<rect x="470" y="420" width="70" height="420" fill="#fff" opacity=".18"/></g>';
        // Glazed cap and sculpted base
        $cap  = 'M515 430 Q520 360 600 350 Q680 360 685 430 Z';
        $base = 'M470 835 L730 835 Q800 960 790 1060 Q785 1088 760 1090 L440 1090 Q415 1088 410 1060 Q400 960 470 835 Z';
        $svg .= "<clipPath id=\"c\"><path d=\"$cap\"/><path d=\"$base\"/></clipPath>";
        $svg .= "<path d=\"$cap\" fill=\"url(#g)\"/><path d=\"$base\" fill=\"url(#g)\"/>";
        $svg .= "<path d=\"M440 960 Q600 990 760 960\" stroke=\"$dark\" stroke-width=\"6\" fill=\"none\" opacity=\".45\"/>";
        $svg .= $speckle('c', [400, 340, 800, 1090]);
        // Warm glow on the wall
        $svg .= "<ellipse cx=\"600\" cy=\"600\" rx=\"330\" ry=\"380\" fill=\"$lava\" opacity=\".08\"/>";
    } else { // tablelamp
        $base = 'M560 720 Q470 760 450 880 Q440 1000 520 1075 Q545 1090 600 1090 Q655 1090 680 1075 Q760 1000 750 880 Q730 760 640 720 Z';
        $svg .= "<clipPath id=\"c\"><path d=\"$base\"/></clipPath><path d=\"$base\" fill=\"url(#g)\"/>";
        $svg .= $speckle('c', [440, 700, 760, 1090]);
        $svg .= '<rect x="590" y="560" width="20" height="165" fill="#B8A98C"/>';
        $svg .= '<path d="M430 590 L480 360 L720 360 L770 590 Z" fill="#EFE6D3" stroke="#D9CCB0" stroke-width="4"/>';
        $svg .= '<path d="M480 360 L720 360 L770 590 L430 590 Z" fill="url(#light)"/>';
        $svg .= '<ellipse cx="600" cy="600" rx="200" ry="30" fill="#FFF3C9" opacity=".55"/>';
    }
    return $svg . '</svg>';
}
