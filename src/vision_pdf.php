<?php
declare(strict_types=1);

/** Embed the very same JPEG in a one-page A4 PDF without recomposing the board. */
function vision_snapshot_pdf(string $jpeg, int $width, int $height): string
{
    [$pageW, $pageH] = $width >= $height ? [841.89, 595.28] : [595.28, 841.89];
    $scale = min(($pageW - 40) / $width, ($pageH - 40) / $height);
    $w = $width * $scale; $h = $height * $scale;
    $content = sprintf("q\n%.3F 0 0 %.3F %.3F %.3F cm\n/Im0 Do\nQ\n", $w, $h, ($pageW - $w) / 2, ($pageH - $h) / 2);
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>', $pageW, $pageH),
        '<< /Type /XObject /Subtype /Image /Width ' . $width . ' /Height ' . $height . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpeg) . ">>\nstream\n" . $jpeg . "\nendstream",
        '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . 'endstream',
    ];
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) { $pdf .= sprintf("%010d 00000 n \n", $offset); }
    return $pdf . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
}
