<?php

// Build small, dependency-free PDF documents for EasyOrder database outputs.
// The writer uses standard PDF fonts so the project remains portable in XAMPP.
class EasyOrderPdfDocument
{
	private $page_width;
	private $page_height;
	private $margin = 36.0;
	private $bottom_margin = 42.0;
	private $pages = array();
	private $commands = "";
	private $cursor_y = 0.0;
	private $title;
	private $subtitle;
	private $page_number = 0;

	public function __construct($title,$subtitle="",$orientation="portrait")
	{
		$is_landscape = strtolower((string)$orientation)==="landscape";
		$this->page_width = $is_landscape ? 841.89 : 595.28;
		$this->page_height = $is_landscape ? 595.28 : 841.89;
		$this->title = (string)$title;
		$this->subtitle = (string)$subtitle;
		$this->addPage();
	}

	// Start a new page and repeat enough context to identify a separated page.
	private function addPage()
	{
		if($this->page_number>0)
		{
			$this->pages[] = $this->commands;
		}

		$this->page_number++;
		$this->commands = "";
		$this->cursor_y = 28.0;
		$this->drawText($this->margin,$this->cursor_y,"EasyOrder",17,true,array(0.69,0.00,0.10));
		$this->drawText($this->page_width-$this->margin,$this->cursor_y+1,$this->page_number===1 ? "PDF OUTPUT" : "CONTINUED",8,true,array(0.35,0.35,0.35),"right");
		$this->cursor_y += 25.0;
		$this->drawText($this->margin,$this->cursor_y,$this->title,15,true,array(0.10,0.10,0.10));
		$this->cursor_y += 21.0;

		if($this->subtitle!=="")
		{
			foreach($this->wrapText($this->subtitle,$this->contentWidth(),8.5) as $line)
			{
				$this->drawText($this->margin,$this->cursor_y,$line,8.5,false,array(0.33,0.33,0.33));
				$this->cursor_y += 11.0;
			}
		}

		$this->drawLine($this->margin,$this->cursor_y+3,$this->page_width-$this->margin,$this->cursor_y+3,array(0.82,0.82,0.82));
		$this->cursor_y += 14.0;
	}

	private function contentWidth()
	{
		return $this->page_width-($this->margin*2);
	}

	// Reserve vertical space and create a new page before content can overlap the footer.
	private function ensureSpace($height)
	{
		if($this->cursor_y+(float)$height>$this->page_height-$this->bottom_margin)
		{
			$this->addPage();
			return true;
		}
		return false;
	}

	// Convert dynamic UTF-8 strings to the Windows-1252 encoding supported by Helvetica.
	// Unsupported characters become visible question marks instead of disappearing silently.
	private function encodeText($value)
	{
		$text = html_entity_decode((string)$value,ENT_QUOTES | ENT_HTML5,"UTF-8");
		$text = preg_replace('/[\r\n\t]+/u',' ',$text);
		$text = preg_replace('/\s{2,}/u',' ',$text);
		$text = trim((string)$text);
		$converted = function_exists("mb_convert_encoding") ? @mb_convert_encoding($text,"Windows-1252","UTF-8") : false;
		if($converted===false && function_exists("iconv"))
		{
			$converted = @iconv("UTF-8","Windows-1252//TRANSLIT",$text);
		}
		if($converted!==false)
		{
			$text = $converted;
		}
		return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$text);
	}

	private function escapeText($value)
	{
		return str_replace(array("\\","(",")"),array("\\\\","\\(","\\)"),$this->encodeText($value));
	}

	// Wrap text using a conservative Helvetica width estimate and split long tokens safely.
	private function wrapText($value,$width,$font_size)
	{
		$text = $this->encodeText($value);
		if($text==="")
		{
			return array("");
		}

		$max_chars = max(1,(int)floor((float)$width/max(1.0,(float)$font_size*0.52)));
		$words = preg_split('/\s+/',trim($text));
		$lines = array();
		$current = "";

		foreach($words as $word)
		{
			while(strlen($word)>$max_chars)
			{
				if($current!=="")
				{
					$lines[] = $current;
					$current = "";
				}
				$lines[] = substr($word,0,$max_chars);
				$word = substr($word,$max_chars);
			}

			$candidate = $current==="" ? $word : $current." ".$word;
			if(strlen($candidate)>$max_chars && $current!=="")
			{
				$lines[] = $current;
				$current = $word;
			}
			else
			{
				$current = $candidate;
			}
		}

		if($current!=="" || !$lines)
		{
			$lines[] = $current;
		}
		return $lines;
	}

	private function drawText($x,$top,$text,$size=10,$bold=false,$colour=array(0,0,0),$align="left")
	{
		$encoded = $this->encodeText($text);
		$estimated_width = strlen($encoded)*(float)$size*0.50;
		if($align==="right")
		{
			$x -= $estimated_width;
		}
		else if($align==="center")
		{
			$x -= $estimated_width/2;
		}

		$baseline = $this->page_height-(float)$top-(float)$size;
		$font = $bold ? "F2" : "F1";
		$this->commands .= sprintf("%.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
			$colour[0],$colour[1],$colour[2],$font,$size,$x,$baseline,$this->escapeText($text));
	}

	private function drawLine($x1,$top1,$x2,$top2,$colour=array(0.8,0.8,0.8),$line_width=0.6)
	{
		$y1 = $this->page_height-(float)$top1;
		$y2 = $this->page_height-(float)$top2;
		$this->commands .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
			$colour[0],$colour[1],$colour[2],$line_width,$x1,$y1,$x2,$y2);
	}

	private function drawRectangle($x,$top,$width,$height,$fill,$stroke=array(0.82,0.82,0.82))
	{
		$y = $this->page_height-(float)$top-(float)$height;
		$this->commands .= sprintf("%.3F %.3F %.3F rg %.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re B\n",
			$fill[0],$fill[1],$fill[2],$stroke[0],$stroke[1],$stroke[2],0.5,$x,$y,$width,$height);
	}

	public function addSectionTitle($title)
	{
		$this->ensureSpace(70);
		$this->cursor_y += 4;
		$this->drawText($this->margin,$this->cursor_y,$title,11,true,array(0.22,0.22,0.22));
		$this->cursor_y += 18;
	}

	public function addParagraph($text)
	{
		$lines = $this->wrapText($text,$this->contentWidth(),9.5);
		$this->ensureSpace((count($lines)*13)+8);
		foreach($lines as $line)
		{
			$this->drawText($this->margin,$this->cursor_y,$line,9.5,false,array(0.22,0.22,0.22));
			$this->cursor_y += 13;
		}
		$this->cursor_y += 6;
	}

	// Render labelled values without exposing fields that callers do not explicitly supply.
	public function addDefinitionList($pairs)
	{
		$label_width = min(130.0,$this->contentWidth()*0.28);
		$value_width = $this->contentWidth()-$label_width;
		$row_index = 0;

		foreach($pairs as $label=>$value)
		{
			$value_lines = $this->wrapText($value,$value_width-12,9);
			$row_height = max(24.0,(count($value_lines)*12.0)+10.0);
			$this->ensureSpace($row_height);
			$fill = $row_index%2===0 ? array(0.98,0.98,0.98) : array(1,1,1);
			$this->drawRectangle($this->margin,$this->cursor_y,$label_width,$row_height,array(0.95,0.95,0.95));
			$this->drawRectangle($this->margin+$label_width,$this->cursor_y,$value_width,$row_height,$fill);
			$this->drawText($this->margin+6,$this->cursor_y+7,$label,8.5,true,array(0.28,0.28,0.28));
			foreach($value_lines as $line_index=>$line)
			{
				$this->drawText($this->margin+$label_width+6,$this->cursor_y+7+($line_index*12),$line,9,false,array(0.12,0.12,0.12));
			}
			$this->cursor_y += $row_height;
			$row_index++;
		}
		$this->cursor_y += 8;
	}

	// Draw a paginated table and repeat its headings on every continuation page.
	public function addTable($headers,$rows,$weights=array())
	{
		$column_count = count($headers);
		if($column_count===0)
		{
			return;
		}

		if(count($weights)!==$column_count || array_sum($weights)<=0)
		{
			$weights = array_fill(0,$column_count,1);
		}
		$weight_total = array_sum($weights);
		$widths = array_map(function($weight) use ($weight_total)
		{
			return $this->contentWidth()*((float)$weight/$weight_total);
		},$weights);

		$draw_header = function() use ($headers,$widths)
		{
			$font_size = 8.2;
			$line_height = 10.2;
			$header_lines = array();
			$max_lines = 1;
			foreach($headers as $index=>$header)
			{
				$header_lines[$index] = $this->wrapText($header,$widths[$index]-8,$font_size);
				$max_lines = max($max_lines,count($header_lines[$index]));
			}
			$height = max(25.0,($max_lines*$line_height)+10.0);
			$x = $this->margin;
			foreach($headers as $index=>$header)
			{
				$this->drawRectangle($x,$this->cursor_y,$widths[$index],$height,array(0.24,0.27,0.31),array(0.24,0.27,0.31));
				foreach($header_lines[$index] as $line_index=>$line)
				{
					$this->drawText($x+4,$this->cursor_y+6+($line_index*$line_height),$line,$font_size,true,array(1,1,1));
				}
				$x += $widths[$index];
			}
			$this->cursor_y += $height;
		};

		if($this->ensureSpace(55))
		{
			// The repeated document heading already explains the page after a break.
		}
		$draw_header();

		if(!$rows)
		{
			$this->ensureSpace(30);
			$this->drawRectangle($this->margin,$this->cursor_y,$this->contentWidth(),28,array(0.98,0.98,0.98));
			$this->drawText($this->margin+6,$this->cursor_y+8,"No records match the selected filters.",9,false,array(0.35,0.35,0.35));
			$this->cursor_y += 36;
			return;
		}

		foreach(array_values($rows) as $row_index=>$row)
		{
			$cells = array_values($row);
			$cell_lines = array();
			$max_lines = 1;
			foreach($headers as $column_index=>$unused)
			{
				$cell_lines[$column_index] = $this->wrapText($cells[$column_index] ?? "",$widths[$column_index]-8,8.1);
				$max_lines = max($max_lines,count($cell_lines[$column_index]));
			}
			$row_height = max(24.0,($max_lines*10.2)+10.0);

			if($this->ensureSpace($row_height+4))
			{
				$draw_header();
			}

			$x = $this->margin;
			$fill = $row_index%2===0 ? array(1,1,1) : array(0.965,0.97,0.975);
			foreach($headers as $column_index=>$unused)
			{
				$this->drawRectangle($x,$this->cursor_y,$widths[$column_index],$row_height,$fill);
				foreach($cell_lines[$column_index] as $line_index=>$line)
				{
					$this->drawText($x+4,$this->cursor_y+6+($line_index*10.2),$line,8.1,false,array(0.12,0.12,0.12));
				}
				$x += $widths[$column_index];
			}
			$this->cursor_y += $row_height;
		}
		$this->cursor_y += 8;
	}

	// Assemble a standards-compliant PDF with dynamic offsets and safe download headers.
	public function download($filename)
	{
		$this->pages[] = $this->commands;
		$page_count = count($this->pages);
		$font_regular_id = 3+($page_count*2);
		$font_bold_id = $font_regular_id+1;
		$objects = array();
		$page_references = array();

		$objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
		foreach($this->pages as $index=>$page_commands)
		{
			$page_id = 3+($index*2);
			$content_id = $page_id+1;
			$page_references[] = $page_id." 0 R";
			$footer = sprintf("Page %d of %d",$index+1,$page_count);
			$footer_commands = "";
			$footer_commands .= sprintf("0.45 0.45 0.45 rg BT /F1 8 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
				$this->margin,24.0,$this->escapeText("Generated ".date("d M Y, h:i A")));
			$footer_commands .= sprintf("0.45 0.45 0.45 rg BT /F1 8 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
				$this->page_width-$this->margin-(strlen($footer)*4.0),24.0,$this->escapeText($footer));
			$stream = $page_commands.$footer_commands;
			$objects[$page_id] = sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>",
				$this->page_width,$this->page_height,$font_regular_id,$font_bold_id,$content_id);
			$objects[$content_id] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream";
		}

		$objects[2] = "<< /Type /Pages /Kids [".implode(" ",$page_references)."] /Count ".$page_count." >>";
		$objects[$font_regular_id] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
		$objects[$font_bold_id] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
		ksort($objects);

		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array(0=>0);
		foreach($objects as $object_id=>$object)
		{
			$offsets[$object_id] = strlen($pdf);
			$pdf .= $object_id." 0 obj\n".$object."\nendobj\n";
		}
		$xref_offset = strlen($pdf);
		$object_count = count($objects)+1;
		$pdf .= "xref\n0 ".$object_count."\n";
		$pdf .= "0000000000 65535 f \n";
		for($object_id=1;$object_id<$object_count;$object_id++)
		{
			$pdf .= sprintf("%010d 00000 n \n",$offsets[$object_id]);
		}
		$pdf .= "trailer\n<< /Size ".$object_count." /Root 1 0 R >>\nstartxref\n".$xref_offset."\n%%EOF\n";

		$safe_filename = preg_replace('/[^A-Za-z0-9._-]/','-',basename((string)$filename));
		if(!str_ends_with(strtolower($safe_filename),".pdf"))
		{
			$safe_filename .= ".pdf";
		}
		header("Content-Type: application/pdf");
		header('Content-Disposition: attachment; filename="'.$safe_filename.'"');
		header("Content-Length: ".strlen($pdf));
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Pragma: no-cache");
		header("X-Content-Type-Options: nosniff");
		echo $pdf;
		exit();
	}
}

// Generate a reusable filtered list PDF from rows already approved by the caller.
function easyorder_pdf_table_download($filename,$title,$subtitle,$headers,$rows,$weights=array())
{
	$pdf = new EasyOrderPdfDocument($title,$subtitle,"landscape");
	$pdf->addSectionTitle("Records");
	$pdf->addTable($headers,$rows,$weights);
	$pdf->download($filename);
}

?>
