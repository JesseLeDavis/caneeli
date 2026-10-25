<?php
/**
 * Homepage hero art: seven sketched sea-creature lava lamps poking out of a
 * tub of rolling water. Hover (or tap) a lamp and it pops up with a splash.
 *
 * Everything is drawn in one 600×630 coordinate space so the water shapes,
 * the lamp mask (in _home.scss) and the splash maths (main.js) line up at any
 * size. If you change the tub outline, change it in all three places.
 *
 * Lamp art lives in /assets/images/hero/*.webp — transparent pencil line
 * work used as a mask, so main.js can fill each one with a site colour.
 * The mask is set inline, root-relative, on purpose. Mask images must be
 * same-origin, and SITE_URL can differ from the visitor's host (www vs bare
 * domain). A url() passed through a CSS variable resolves against the
 * stylesheet's SITE_URL origin instead of the page, and silently vanishes.
 */

// x: where it rises from · tilt: fan angle · sink: how buried at rest · delay: bob offset
$heroLamps = [
    ['barracuda', 31, -22, 7, -0.4],
    ['shark',     38, -14, 2, -1.9],
    ['slug',      45,  -8, 9, -3.1],
    ['squid',     52,   0, 0, -0.9],
    ['moray',     59,   7, 5, -2.5],
    ['trout',     66,  15, 3, -1.3],
    ['blobfish',  73,  22, 8, -3.6],
];
?>
<div class="lamp-tub" aria-hidden="true">
    <svg class="lamp-tub__layer lamp-tub__water--back" viewBox="0 0 600 630">
        <defs>
            <clipPath id="lamp-tub-back"><path d="M30 340 H570 V440 A270 178 0 0 1 30 440 Z"/></clipPath>
            <clipPath id="lamp-tub-below-back"><path d="M-330.00 418 Q-285.00 388 -240.00 418 T-150.00 418 T-60.00 418 T30.00 418 T120.00 418 T210.00 418 T300.00 418 T390.00 418 T480.00 418 T570.00 418 T660.00 418 T750.00 418 T840.00 418 T930.00 418 T1020.00 418 T1110.00 418 V700 H-330.00 Z"><animateTransform attributeName="transform" type="translate" from="180.0000 0" to="0 0" dur="12s" repeatCount="indefinite"/></path></clipPath>
        </defs>
        <g clip-path="url(#lamp-tub-back)">
            <g clip-path="url(#lamp-tub-below-back)"><rect x="0" y="340" width="600" height="290" fill="#C1E1FA"/></g>
            <path class="lamp-tub__ink" fill="none" stroke="#2D2D2D" stroke-width="3" d="M-330.00 418 Q-285.00 388 -240.00 418 T-150.00 418 T-60.00 418 T30.00 418 T120.00 418 T210.00 418 T300.00 418 T390.00 418 T480.00 418 T570.00 418 T660.00 418 T750.00 418 T840.00 418 T930.00 418 T1020.00 418 T1110.00 418"><animateTransform attributeName="transform" type="translate" from="180.0000 0" to="0 0" dur="12s" repeatCount="indefinite"/></path>
        </g>
        <g clip-path="url(#lamp-tub-below-back)"><path class="lamp-tub__ink" fill="none" stroke="#2D2D2D" stroke-width="3" d="M570 360 V440 A270 178 0 0 1 30 440 V360"/></g>
    </svg>

    <div class="lamp-tub__lamps">
        <div class="lamp-tub__inner">
            <?php foreach ($heroLamps as [$name, $x, $tilt, $sink, $delay]): ?>
                <div class="lamp-tub__lamp" tabindex="0"
                     style="--x:<?php echo $x; ?>%; --tilt:<?php echo $tilt; ?>deg; --sink:<?php echo $sink; ?>%; --d:<?php echo $delay; ?>s">
                    <?php $art = "url(/assets/images/hero/$name.webp)"; ?>
                    <span class="lamp-tub__art" style="-webkit-mask-image:<?php echo $art; ?>; mask-image:<?php echo $art; ?>"></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <svg class="lamp-tub__layer lamp-tub__water--front" viewBox="0 0 600 630">
        <defs>
            <clipPath id="lamp-tub-front"><path d="M30 360 H570 V440 A270 178 0 0 1 30 440 Z"/></clipPath>
            <clipPath id="lamp-tub-below"><path d="M-278.57 440 Q-240.00 404 -201.43 440 T-124.29 440 T-47.14 440 T30.00 440 T107.14 440 T184.29 440 T261.43 440 T338.57 440 T415.71 440 T492.86 440 T570.00 440 T647.14 440 T724.29 440 T801.43 440 T878.57 440 T955.71 440 T1032.86 440 T1110.00 440 V700 H-278.57 Z"><animateTransform attributeName="transform" type="translate" from="0 0" to="154.2857 0" dur="9s" repeatCount="indefinite"/></path></clipPath>
        </defs>
        <g clip-path="url(#lamp-tub-front)">
            <g clip-path="url(#lamp-tub-below)"><rect x="0" y="360" width="600" height="270" fill="#6497D6"/></g>
            <path class="lamp-tub__ink" fill="none" stroke="#2D2D2D" stroke-width="3" d="M-278.57 440 Q-240.00 404 -201.43 440 T-124.29 440 T-47.14 440 T30.00 440 T107.14 440 T184.29 440 T261.43 440 T338.57 440 T415.71 440 T492.86 440 T570.00 440 T647.14 440 T724.29 440 T801.43 440 T878.57 440 T955.71 440 T1032.86 440 T1110.00 440"><animateTransform attributeName="transform" type="translate" from="0 0" to="154.2857 0" dur="9s" repeatCount="indefinite"/></path>
        </g>
        <!-- Walls + bottom at the same weight as the surface, ending where the surface meets them. -->
        <g clip-path="url(#lamp-tub-below)"><path class="lamp-tub__ink" fill="none" stroke="#2D2D2D" stroke-width="3" d="M570 360 V440 A270 178 0 0 1 30 440 V360"/></g>
    </svg>

    <div class="lamp-tub__splash"></div>
</div>
