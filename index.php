<?php
/*
This file is licensed under the MIT license.

Copyright (c) 2015 Marius Neugebauer

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

// config
$config = [

    // the maximum level for splitting
    // it results in 2^(max-level) rows and columns
    // example:
    //     max-level of 4 results in
    //     2^4=16 rows and columns and
    //     a total of 16x16=256 tiles
    'max-level' => 4

];

// if file was uploaded
if (!empty($_FILES['file'])) {


    // prepare splitting

    // accepting level 1 to [max-level]
    $level = empty($_POST['level']) ? 1 : (int) $_POST['level'];
    $level = min($config['max-level'], max(1, $level));
    // the number of rows and columns
    $format = 1 << $level;
    // the total number of tiles
    $numTiles = $format * $format;


    // load file

    // get some info about the uploaded file
    $path     = $_FILES['file']['tmp_name'];
    $filename = $_FILES['file']['name'];

    // create DOM document from file
    $dom = new DOMDocument();
    $dom->load($path);

    // create another one where we later put the results
    $newDom = new DOMDocument();
    $newDom->load($path);


    // action

    // find every <flame> and split it
    $flames = $dom->getElementsByTagName('flame');
    foreach ($flames as $flame) {

        // check if it has the right type
        if (!$flame instanceof DOMElement) {
            continue;
        }

        // retrieving attributes

        $name = $flame->getAttribute('name');
        // size and center are each two values delimited by a space
        $size   = explode(' ', $flame->getAttribute('size'));
        $width  = (int) $size[0];
        $height = (int) $size[1];
        $center = explode(' ', $flame->getAttribute('center'));
        $posX   = (double) $center[0];
        $posY   = (double) $center[1];
        $zoom   = (double) $flame->getAttribute('zoom');
        $scale  = (double) $flame->getAttribute('scale');
        $rotate = deg2rad((double) $flame->getAttribute('rotate'));

        // calculating

        $aspectRatio = $height / $width;
        // zoom of 1 means that the dimensions are halved, 2 means that they are a quarter the length, ...
        // ... but we need the factor
        $zoomFactor = pow(2, $zoom);
        // the scale factor is the horizontal offset without considering rotation
        $scaleFactor = 1 / $scale * $width / $format / $zoomFactor;
        $sinFactor   = sin($rotate);
        $cosFactor   = cos($rotate);

        // since the viewpoint can be rotated we have to express the horizontal and vertical offset ...
        // ... as vectors on the actual flame
        // horizontal offset considering rotation
        $offsetX = [
            $scaleFactor * $cosFactor,
            -1 * $scaleFactor * $sinFactor,
        ];
        // vertical offset considering roation
        $offsetY = [
            $scaleFactor * $aspectRatio * $sinFactor,
            $scaleFactor * $aspectRatio * $cosFactor,
        ];

        // how many tiles we have to go left and up for the center of the first tile
        $tileOffset = ($format / 2) - 0.5;

        for ($i = 0; $i < $numTiles; ++$i) {
            $tileX   = $i % $format;
            $tileY   = (int) ($i / $format);
            $xFactor = $tileX - $tileOffset;
            $yFactor = $tileY - $tileOffset;

            // new attributes
            $newScale = $scale * $format;
            // just add the offset vectors to the position
            $newPosX = $posX + $xFactor * $offsetX[0] + $yFactor * $offsetY[0];
            $newPosY = $posY + $xFactor * $offsetX[1] + $yFactor * $offsetY[1];

            // create flame DOM
            $newFlame = $newDom->importNode($flame, true);
            if ($newFlame instanceof DOMElement) {
                $newFlame->setAttribute('name', $name . ' (' . $tileX . 'x' . $tileY . ')');
                $newFlame->setAttribute('center', $newPosX . ' ' . $newPosY);
                $newFlame->setAttribute('scale', $newScale);
            }

            // add flame to the set
            $root = $newDom->getElementsByTagName('flames')->item(0);
            $root->appendChild($newFlame);

        }

    }

    // get the XML
    $xml = $newDom->saveXML();
    // add newline after every flame (Apophysis7X has trouble otherwise as it seems)
    $xml = str_replace('</flame>', "</flame>\n", $xml);
    // remove first line
    // I never saw the XML header in any flame, even though Apophysis7X has no problem with it
    $xml = preg_replace('/^.+\n/', '', $xml);

    // gather info
    $pathInfo = pathinfo($filename);
    $filename = addcslashes(basename($pathInfo['filename']), '"\\') . '_' . $format . 'x' . $format . '.flame';
    $size     = strlen($xml);

    // send headers
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/octet-stream');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . $size);

    // send content and exit
    echo $xml;
    exit;

}


?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ApophysisSplitter</title>
</head>
<body>

<form method="post" enctype="multipart/form-data">

    <input type="file" name="file"/>

    <select name="level">
        <?php for ($x = 1; $x <= $config['max-level']; ++$x) {
            $format = 1 << $x;
            echo '<option value="' . $x . '">' . $format . 'x' . $format . '</option>';
        } ?>
    </select>

    <input type="submit" value="Go"/>

</form>

</body>
</html>