<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2016 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Out\Tool;

class PhraseaPDF extends \TCPDF
{
    const FONT = 'freesans';

    public function Header()
    {

    }

    public function Footer()
    {
        $this->SetLeftMargin(0);
        $mr = $this->SetRightMargin(0);

        $this->SetY(-15);

        $this->SetFont(self::FONT, 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');

        $this->SetFont(self::FONT, '', 8);
        $w = $this->GetStringWidth('Généré par');

        $this->SetFont(self::FONT, 'B', 8);
        $w += $this->GetStringWidth(' Gemnet');

        $this->SetXY(-$w - $mr - 5, -15);

        $this->SetFont(self::FONT, '', 8);
        $this->Write(8, 'Généré par');

        $this->SetFont(self::FONT, 'B', 8);
        $this->Write(8, ' Gemnet');
    }
}
