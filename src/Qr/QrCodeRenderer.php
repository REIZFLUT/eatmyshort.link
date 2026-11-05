<?php

declare(strict_types=1);

namespace App\Qr;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class QrCodeRenderer
{
    public function svg(string $data): string
    {
        return new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'svgAddXmlHeader' => true,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgUseFillAttributes' => true,
        ]))->render($data);
    }

    public function png(string $data, int $scale = 12): string
    {
        return new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
        ]))->render($data);
    }
}
