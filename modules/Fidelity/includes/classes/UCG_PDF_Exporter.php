<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

class UCG_PDF_Exporter {
    protected $title;
    protected $headers = [];
    protected $rows = [];
    protected $messages = [];
    protected $columnPositions;
    protected $lineHeight = 16;
    protected $topMargin = 760;
    protected $bottomMargin = 60;
    protected $images = [];
    protected $pageMeta = [];
    protected $pageWidth = 612;
    protected $pageHeight = 792;

    public function __construct($title = '', array $columnPositions = []){
        $this->title = $title;
        $this->columnPositions = !empty($columnPositions) ? array_values($columnPositions) : [40, 140, 280, 420, 500, 560];
    }

    public function setHeaders(array $headers){
        $this->headers = array_map('strval', $headers);
    }

    public function addRow(array $row){
        $this->rows[] = array_map('strval', $row);
    }

    public function addMessage($message){
        if($message !== ''){
            $this->messages[] = (string)$message;
        }
    }

    public function addImage($filePath, array $options = array()){
        $path = (string)$filePath;
        if($path === '' || !file_exists($path)){
            return false;
        }

        $info = @getimagesize($path);
        if(!$info || empty($info[0]) || empty($info[1])){
            return false;
        }

        $width = (int)$info[0];
        $height = (int)$info[1];
        if($width <= 0 || $height <= 0){
            return false;
        }

        $data = @file_get_contents($path);
        if($data === false){
            return false;
        }

        $filter = '/DCTDecode';
        $colorSpace = '/DeviceRGB';
        $bits = isset($info['bits']) ? (int)$info['bits'] : 8;
        $decodeParms = '';

        $pngData = strncmp($data, "\x89PNG\r\n\x1a\n", 8) === 0 ? $this->preparePngImage($data) : null;

        if($pngData){
            $data = $pngData['data'];
            $filter = '/FlateDecode';
            $colorSpace = $pngData['color_space'];
            $bits = $pngData['bits'];
            $decodeParms = $pngData['decode_parms'];
            $width = $pngData['width'];
            $height = $pngData['height'];
        } elseif((int)$info[2] !== IMAGETYPE_JPEG){
            if(!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')){
                return false;
            }

            $imageResource = @imagecreatefromstring($data);
            if(!$imageResource){
                return false;
            }

            ob_start();
            imagejpeg($imageResource, null, 90);
            $data = ob_get_clean();
            imagedestroy($imageResource);

            if($data === false){
                return false;
            }

            $bits = 8;
        } elseif(!empty($info['channels']) && (int)$info['channels'] === 1){
            $colorSpace = '/DeviceGray';
        }

        $displayWidth = isset($options['width']) ? (float)$options['width'] : 200.0;
        if($displayWidth <= 0){
            $displayWidth = 200.0;
        }

        $displayHeight = isset($options['height']) ? (float)$options['height'] : ($displayWidth * ($height / $width));
        if($displayHeight <= 0){
            $displayHeight = 200.0;
        }

        $margin = isset($options['margin_bottom']) ? (float)$options['margin_bottom'] : 24.0;
        if($margin < 0){
            $margin = 24.0;
        }

        $x = isset($options['x']) ? (float)$options['x'] : null;
        $y = isset($options['y']) ? (float)$options['y'] : null;

        $this->images[] = array(
            'data' => $data,
            'width' => $width,
            'height' => $height,
            'display_width' => $displayWidth,
            'display_height' => $displayHeight,
            'color_space' => $colorSpace,
            'bits' => $bits,
            'filter' => $filter,
            'decode_parms' => $decodeParms,
            'margin_bottom' => $margin,
            'x' => $x,
            'y' => $y,
            'page' => null,
            'name' => null,
        );

        return true;
    }

    protected function preparePngImage($data){
        if(strlen($data) < 8 || strncmp($data, "\x89PNG\r\n\x1a\n", 8) !== 0){
            return null;
        }

        $offset = 8;
        $length = strlen($data);
        $width = $height = $bitDepth = $colorType = null;
        $idat = '';

        while($offset + 8 <= $length){
            $chunkLengthData = substr($data, $offset, 4);
            if(strlen($chunkLengthData) !== 4){
                return null;
            }

            $chunkLength = unpack('N', $chunkLengthData)[1];
            $chunkType = substr($data, $offset + 4, 4);
            $offset += 8;

            if($offset + $chunkLength > $length){
                return null;
            }

            $chunkData = substr($data, $offset, $chunkLength);
            $offset += $chunkLength + 4; // Salta i dati e il CRC

            if($chunkType === 'IHDR'){
                if($chunkLength !== 13){
                    return null;
                }

                $unpacked = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $chunkData);
                if(empty($unpacked['width']) || empty($unpacked['height'])){
                    return null;
                }

                $width = (int)$unpacked['width'];
                $height = (int)$unpacked['height'];
                $bitDepth = (int)$unpacked['bitDepth'];
                $colorType = (int)$unpacked['colorType'];

                if($unpacked['compression'] !== 0 || $unpacked['filter'] !== 0 || $unpacked['interlace'] !== 0){
                    return null;
                }
            } elseif($chunkType === 'IDAT'){
                $idat .= $chunkData;
            } elseif($chunkType === 'IEND'){
                break;
            }
        }

        if($width === null || $height === null || $bitDepth === null || $colorType === null || $idat === ''){
            return null;
        }

        if(!in_array($colorType, [0, 2], true)){
            return null;
        }

        $colors = $colorType === 2 ? 3 : 1;
        $colorSpace = $colorType === 2 ? '/DeviceRGB' : '/DeviceGray';

        $decodeParms = '<< /Predictor 15 /Colors ' . $colors . ' /BitsPerComponent ' . $bitDepth . ' /Columns ' . $width . ' >>';

        return array(
            'width' => $width,
            'height' => $height,
            'bits' => $bitDepth,
            'color_space' => $colorSpace,
            'data' => $idat,
            'decode_parms' => $decodeParms,
        );
    }

    public function output($filename){
        $pages = $this->generatePages();
        $pdf = $this->buildPdf($pages);

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));

        echo $pdf;
        exit;
    }

    public function render(){
        $pages = $this->generatePages();
        return $this->buildPdf($pages);
    }

    protected function generatePages(){
        $pages = [];
        $this->pageMeta = [];
        $currentContent = '';
        $y = $this->topMargin;
        $isFirstPage = true;
        $currentPageIndex = 0;

        $startPage = function() use (&$currentContent, &$y, &$isFirstPage, &$currentPageIndex, &$pages){
            $currentContent = '';
            $y = $this->topMargin;
            $isFirstPage = count($pages) === 0;
            $currentPageIndex = count($pages);
            $this->pageMeta[$currentPageIndex] = array('y' => $y);

            if($isFirstPage && $this->title){
                $currentContent .= $this->textLine($this->title, 16, 40, $y);
                $y -= ($this->lineHeight * 1.5);
            }

            if(!empty($this->headers)){
                $currentContent .= $this->tableRow($this->headers, true, $y);
                $y -= $this->lineHeight;
            }
        };

        $startPage();

        if(empty($this->rows) && !empty($this->messages)){
            foreach($this->messages as $message){
                if($y < $this->bottomMargin){
                    $this->pageMeta[$currentPageIndex]['y'] = $y;
                    $pages[] = $currentContent;
                    $startPage();
                }
                $currentContent .= $this->textLine($message, 12, 40, $y);
                $y -= $this->lineHeight;
            }
        } elseif(empty($this->rows)){
            if($y < $this->bottomMargin){
                $this->pageMeta[$currentPageIndex]['y'] = $y;
                $pages[] = $currentContent;
                $startPage();
            }
            $currentContent .= $this->textLine('Nessun dato disponibile.', 12, 40, $y);
            $y -= $this->lineHeight;
        } else {
            foreach($this->rows as $row){
                if($y < $this->bottomMargin){
                    $this->pageMeta[$currentPageIndex]['y'] = $y;
                    $pages[] = $currentContent;
                    $startPage();
                }
                $currentContent .= $this->tableRow($row, false, $y);
                $y -= $this->lineHeight;
            }
        }

        if(!empty($this->images)){
            foreach($this->images as $index => $image){
                $displayWidth = (float)$image['display_width'];
                $displayHeight = (float)$image['display_height'];
                $maxWidth = $this->pageWidth - 80;
                if($displayWidth > $maxWidth){
                    $scale = $maxWidth / $displayWidth;
                    $displayWidth = $maxWidth;
                    $displayHeight = $displayHeight * $scale;
                }

                if($displayHeight <= 0){
                    continue;
                }

                $margin = isset($image['margin_bottom']) ? (float)$image['margin_bottom'] : 24.0;
                $requiredSpace = $displayHeight + $margin;
                if($y - $requiredSpace < $this->bottomMargin){
                    $this->pageMeta[$currentPageIndex]['y'] = $y;
                    $pages[] = $currentContent;
                    $startPage();
                }

                $x = $image['x'];
                if($x === null){
                    $x = ($this->pageWidth - $displayWidth) / 2;
                    if($x < 40){
                        $x = 40;
                    }
                }

                $y -= $displayHeight;
                if($y < $this->bottomMargin){
                    $y = $this->bottomMargin;
                }

                $imgY = $y;
                $this->images[$index]['display_width'] = $displayWidth;
                $this->images[$index]['display_height'] = $displayHeight;
                $this->images[$index]['page'] = $currentPageIndex;
                $this->images[$index]['name'] = 'Im' . ($index + 1);
                $this->images[$index]['x_position'] = $x;
                $this->images[$index]['y_position'] = $imgY;

                $currentContent .= $this->imageCommand($this->images[$index]['name'], $x, $imgY, $displayWidth, $displayHeight);

                $y = $imgY - $margin;
            }
        }

        $this->pageMeta[$currentPageIndex]['y'] = $y;
        $pages[] = $currentContent;

        return $pages;
    }

    protected function tableRow(array $columns, $isHeader, $y){
        $fontSize = $isHeader ? 12 : 10;
        $content = '';
        $count = min(count($columns), count($this->columnPositions));

        for($i = 0; $i < $count; $i++){
            $content .= $this->textLine($columns[$i], $fontSize, $this->columnPositions[$i], $y);
        }

        return $content;
    }

    protected function textLine($text, $fontSize, $x, $y){
        $encoded = $this->encodeText($text);
        $escaped = $this->escapeText($encoded);
        return 'BT /F1 ' . $fontSize . ' Tf ' . $x . ' ' . $y . ' Td (' . $escaped . ') Tj ET' . "\n";
    }

    protected function encodeText($text){
        if(function_exists('mb_convert_encoding')){
            return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        }

        if(function_exists('iconv')){
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
            if($converted !== false){
                return $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $text);
    }

    protected function escapeText($text){
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text ?? '');
    }

    protected function imageCommand($name, $x, $y, $width, $height){
        $width = $this->formatNumber($width);
        $height = $this->formatNumber($height);
        $x = $this->formatNumber($x);
        $y = $this->formatNumber($y);

        return 'q ' . $width . ' 0 0 ' . $height . ' ' . $x . ' ' . $y . ' cm /' . $name . ' Do Q' . "\n";
    }

    protected function formatNumber($value){
        if(!is_numeric($value)){
            return '0';
        }

        $formatted = sprintf('%.3F', (float)$value);
        return rtrim(rtrim($formatted, '0'), '.');
    }

    protected function buildPdf(array $pages){
        $pageCount = max(1, count($pages));
        $pageObjectStart = 3;
        $contentObjectStart = $pageObjectStart + $pageCount;
        $fontObjectId = $contentObjectStart + $pageCount;
        $imageCount = count($this->images);
        $imageObjectStart = $fontObjectId + 1;
        $totalObjects = $fontObjectId + $imageCount;

        $objects = [];

        $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";

        $kids = [];
        for($i = 0; $i < $pageCount; $i++){
            $kids[] = ($pageObjectStart + $i) . ' 0 R';
        }
        $objects[] = '2 0 obj << /Type /Pages /Kids [ ' . implode(' ', $kids) . ' ] /Count ' . $pageCount . " >> endobj\n";

        for($i = 0; $i < $pageCount; $i++){
            $pageId = $pageObjectStart + $i;
            $contentId = $contentObjectStart + $i;
            $resource = '<< /Font << /F1 ' . $fontObjectId . ' 0 R >>';

            $pageImages = [];
            foreach($this->images as $index => $image){
                if(isset($image['page']) && $image['page'] === $i && !empty($image['data'])){
                    $pageImages['/Im' . ($index + 1)] = ($imageObjectStart + $index) . ' 0 R';
                }
            }

            if(!empty($pageImages)){
                $resource .= ' /XObject << ';
                foreach($pageImages as $name => $ref){
                    $resource .= $name . ' ' . $ref . ' ';
                }
                $resource .= '>>';
            }

            $resource .= ' >>';

            $objects[] = $pageId . ' 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->pageWidth . ' ' . $this->pageHeight . '] /Contents ' . $contentId . ' 0 R /Resources ' . $resource . ' >> endobj' . "\n";
        }

        for($i = 0; $i < $pageCount; $i++){
            $contentId = $contentObjectStart + $i;
            $stream = $pages[$i];
            $length = strlen($stream);
            $objects[] = $contentId . ' 0 obj << /Length ' . $length . " >>\nstream\n" . $stream . "endstream\nendobj\n";
        }

        $objects[] = $fontObjectId . " 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";

        for($i = 0; $i < $imageCount; $i++){
            $image = $this->images[$i];
            if(empty($image['data']) || empty($image['width']) || empty($image['height'])){
                continue;
            }

            $length = strlen($image['data']);
            $imageId = $imageObjectStart + $i;
            $object = $imageId . ' 0 obj << /Type /XObject /Subtype /Image /Width ' . (int)$image['width'] . ' /Height ' . (int)$image['height'] . ' /ColorSpace ' . $image['color_space'] . ' /BitsPerComponent ' . (int)$image['bits'] . ' /Filter ' . $image['filter'];

            if(!empty($image['decode_parms'])){
                $object .= ' /DecodeParms ' . $image['decode_parms'];
            }

            $object .= ' /Length ' . $length . " >>\nstream\n" . $image['data'] . "\nendstream\nendobj\n";
            $objects[] = $object;
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        $offset = strlen($pdf);

        foreach($objects as $object){
            $offsets[] = $offset;
            $pdf .= $object;
            $offset += strlen($object);
        }

        $xrefPosition = $offset;
        $pdf .= 'xref\n0 ' . ($totalObjects + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach($offsets as $objectOffset){
            $pdf .= sprintf('%010d 00000 n %s', $objectOffset, "\n");
        }

        $pdf .= 'trailer << /Size ' . ($totalObjects + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefPosition . "\n";
        $pdf .= '%%EOF';

        return $pdf;
    }
}
