<?php /* Copyright (C) 2017 David O'Riva. MIT License.
       * Original: https://github.com/starekrow/safedown
       ********************************************************/

/*
================================================================================

Safedown - Converts text to "safe" HTML with some styling

This converts text to HTML using a restricted subset of [markdown][1]. No inline 
HTML is allowed at all. Links are supported but disabled by default. Designed 
to be safe with unrestricted user input.

Use:
	$sd = new Safedown();
	echo $sd->text( "Safedown is *awesome*." );

Inline styles:
  * "*text*", "**text**" - italic, bold
  * "_text_", "__text__" - italic, bold
  * "[link](url)" - links
  * "http:..." - autolinking of valid URLs
  * < > & - always converted to entities

  * TODO: "~~text~~" - strikethrough
  * TODO: "^^text^^" - superscript
  * TODO: ",,text,," - subscript
  * TODO: reference-style links
  * TODO: "@name" name tags
  * TODO: "#tag" hash tags 

Block styles:
  * "> " - blockquote
  * indented text - preformat
  * paragraphs - folded
  * "* " (or "- " or "+ ") - bullet
  * TODO: code styling
  * TODO: headers

[1]: https://daringfireball.net/projects/markdown/syntax

================================================================================
*/

class Safedown
{
	const B_NONE = 0;
	const B_EMPTY = 1;
	const B_PRE = 2;
	const B_INDENT = 2;
	const B_PARA = 3;
	const B_LIST = 4;
	const B_QUOTE = 5;
	const B_HEADER = 6;
	const B_EOF = 7;

	protected $filterLinks;
	protected $filterHashTags;		// TODO: "#tag" in text
	protected $filterNameTags;		// TODO: "@tag" in text

	/*
	=====================
	__construct
	=====================
	*/
	public function __construct( $options = null )
	{
		if (!$options) {
			return;
		}
		foreach ($options as $k => $v) {
			switch ($k) {
			case "filterLinks":
				$this->$k = $v;
				break;
			}
		}
	}

	protected static $re_bold = [
		 "*" => "/[*][*]((?:\\\\[*]|[^*]|[*][^*]*[*])+?)[*][*](?![*])/A"
		,"_" => "/[_][_]((?:\\\\[_]|[^_]|[_][^_]*[_])+?)[_][_](?![_])/A"
	];
	protected static $re_em = [
		 "*" => "/[*]((?:\\\\[*]|[^*]|[*][*][^*]*[*][*])+?)[*](?![*])/A"
		,"_" => "/[_]((?:\\\\[_]|[^_]|[_][_][^_]*[_][_])+?)[_](?![_])/A"
	];
	protected static $re_entity = 
		"/&(?:[a-zA-Z][a-zA-Z0-9]+|#[0-9]+|#[xX][0-9a-fA-F]+);/A";
	protected static $re_http = 
		"/(?<=\\W)https?:([^?# \t]+?(?:[?][^# \t]*?)?(?:#[^ \t]*?)?)(?=\\.?[ \t]|\\.?$)/A";
	protected static $re_ftp = 
		"/(?<=\\W)ftp?:([^?# \t]+(?:[?][^# \t]*)?(?:#[^ \t]*)?)(?=\\.?[ \t]|\\.?$)/A";
	protected static $re_link = 
		"/\[([^\\]\\[]+)\](?:\(([^()]+)(?:[ \t]+\"([^\"]*)\")?\)|\[([a-zA-Z0-9.,_]*)\])/A";

	/*
	=====================
	MangleLink
	=====================
	*/
	protected function MangleLink( $link )
	{
		$u = $link["url"];
		$u = substr( $u, 0, 1 ) . "xx" . 
			str_replace( ":", " ", substr( $u, 3 ) );
		$link["text"] = $u;
		$link["url"] = null;
		return $link;
	}

	/*
	=====================
	HandleLink
	=====================
	*/
	protected function HandleLink( $link )
	{
		if (is_string( $link )) {
			$link = [
				"text" => $link
				,"url" => $link
			];
		}
		$got = false;
		$f = $this->filterLinks;
		if ($f) {
			$got = $f( $link );
		}
		if (!$got) {
			$got = $this->MangleLink( $link );
		}
		if ($got !== true) {
			foreach ($got as $k => $v) {
				if (!is_string( $v ) && !is_null( $v )) {
					continue;
				}
				switch ($k) {
				case "url":
				case "text":
				case "title":
				case "click":
					$link[ $k ] = $v;
				}
			}
		}
		if (!isset( $link["url"] ) && !isset( $link["click"] )) {
			return $this->HandleInlines( $link["text"] );
		}
		$put = "<a";
		if (isset( $link["url"] )) {
			$put .= " href=\"";
			$put .= htmlspecialchars( $link["url"] );
			$put .= "\"";
		}
		if (isset( $link["title"] )) {
			$put .= " title=\"" . htmlspecialchars( $link["title"] ) . "\"";
		}
		if (isset( $link["click"] )) {
			$put .= " onclick=\"" . htmlspecialchars( $link["click"] ) . "\"";
		}
		$put .= ">";
		$put .= $this->HandleInlines( $link["text"] );
		$put .= "</a>";
		return $put;
	}

	/*
	=====================
	HandleInlines
	=====================
	*/
	protected function HandleInlines( $text )
	{
		$res = "";
		$scan = 0;
		$last = 0;
		$limit = strlen( $text );
		for (;;) {
			$scan += strcspn( $text, "\\*_[<>&:", $scan );
			if ($scan >= $limit) {
				break;
			}
			$at = $scan;
			$put = null;
			switch ($text[$scan++]) {
			case "\\":
				if ($scan < $limit) {
					switch ($text[$scan]) {
					case "\\":
					case "*":
					case "[":
						$put = $text[$scan++];
					}
				}
				break;

			case "*":
			case "_":
				$c = $text[$scan - 1];
				if ($scan < $limit && $text[$scan] == $c
					&& preg_match( self::$re_bold[$c], $text, $r, 0, $scan - 1 )) {
					$scan += strlen( $r[0] ) - 1;
					$t = $this->HandleInlines( $r[1] );
					$put .= "<strong>$t</strong>";
				} else if (
					preg_match( self::$re_em[$c], $text, $r, 0, $scan - 1 )) {
					$scan += strlen( $r[0] ) - 1;
					$t = $this->HandleInlines( $r[1] );
					$put .= "<em>$t</em>";
				}
				break;

			case "<":
				$put = "&lt;";
				break;

			case ">":
				$put = "&gt;";
				break;

			case "&":
				if (!preg_match( self::$re_entity, $text, $r, 0, $scan - 1 )) {
					$put = "&amp;";
				}
				break;

			case "[":
				if (preg_match( self::$re_link, $text, $r, 0, $scan - 1 )) {
					$scan += strlen( $r[0] ) - 1;
					$l = [
						 "text" => $r[1]
						,"url" => $r[2]
					];
					if (isset( $r[3] ) && $r[3] !== "") {
						$l["title"] = $r[3];
					}
					$put = $this->HandleLink( $l );
				}
				break;

			case ":":		// autolinks
				if ($scan - $last >= 5 && $text[ $scan - 5 ] == 'h' &&
					preg_match( self::$re_http, $text, $r, 0, $scan - 5 )) {
					$at -= 4;
					$scan += strlen( $r[0] ) - 5;
					$link = "http:" . $r[1];
				} else if ($scan - $last >= 6 && $text[ $scan - 6 ] == 'h' &&
					preg_match( self::$re_http, $text, $r, 0, $scan - 6 )) {
					$at -= 5;
					$scan += strlen( $r[0] ) - 6;
					$link = "https:" . $r[1];
				} else if ($scan - $last >= 4 && $text[ $scan - 4 ] == 'f' &&
					preg_match( self::$re_ftp, $text, $r, 0, $scan - 4 )) {
					$at -= 3;
					$scan += strlen( $r[0] ) - 4;
					$link = "ftp:" . $r[1];
				} else {
					break;
				}
				if ($link !== null) {
					$put = $this->HandleLink( $link );
				}
			}
			if ($put !== null) {
				if ($at != $last) {
					$res .= substr( $text, $last, $at - $last );
				}
				$res .= $put;
				$last = $scan;
			}
		}
		if (!$last) {
			return $text;
		}
		if ($last) {
			if ($scan > $last) {
				$res .= substr( $text, $last );
			}
			return $res;
		}
		return $text;
	}

	/*
	=====================
	ClassifyLine
	=====================
	*/
	protected function ClassifyLine( $text, &$pos )
	{
		$scan = $pos[0];
		$indent = 0;
		for (;;) {		
			if (!isset( $text[$scan] )) {
				return self::B_EMPTY;
			}
			$c = $text[$scan++];
			if ($c == "\t") {
				$pos[0] = $scan;
				return self::B_INDENT;
			}
			if ($c == ' ') {
				if (++$indent == 4) {
					$pos[0] = $scan;
					return self::B_INDENT;
				}
				continue;
			}
			if ($c == ">") {
				if (isset( $text[ $scan ] ) && (
					$text[$scan] == " " || $text[$scan] == "\t")) {
					++$scan;
				}
				$pos[0] = $scan;
				return self::B_QUOTE;
			}
			if ($c == "*" || $c == "-" || $c == "+") {
				if (isset( $text[ $scan ] ) && (
					$text[$scan] == " " || $text[$scan] == "\t")) {
					++$scan;
					$pos[0] = $scan;
					return self::B_LIST;
				}
			}
			if ($c == "\r") {
				if (isset( $text[ $scan ] ) && $text[$scan] == "\n") {
					++$scan;
				}
				return self::B_EMPTY;
			}
			if ($c == "\n") {
				return self::B_EMPTY;
			}
			--$scan;
			return self::B_PARA;
		}
	}

	/*
	=====================
	HandleBlock
	=====================
	*/
	protected function HandleBlock( $lines, $file )
	{
		$block = null;
		$out = "";
		$text = "";
		$break = 0;
		$start = 0;
		for ($i = 0, $ic = count( $lines ); $i <= $ic; ++$i) {
			if ($i == $ic) {
				$p = self::B_EOF;
			} else {
				$p = $this->ClassifyLine( $file, $lines[$i] );
			}
			if ($p == self::B_EMPTY) {
				++$break;
				continue;
			}
			// Continue the current block
			switch ($block) {
			case self::B_PRE:
				if ($p == self::B_INDENT) {
					$l = $lines[ $i ];
					if ($break) {
						$text .= str_repeat( "\n", $break );
						$break = 0;
					}
					$text .= substr( $file, $l[0], $l[1] - $l[0] ) . "\n";
					continue 2;
				}
				break;

			case self::B_PARA:
				if ($p == self::B_PARA && !$break) {
					$l = $lines[ $i ];
					$text .= " " . substr( $file, $l[0], $l[1] - $l[0] );
					continue 2;
				}
				break;
			case self::B_QUOTE:
				if ($p == self::B_QUOTE || ($p == self::B_PARA && !$break)) {
					continue 2;
				}
				break;
			case self::B_LIST:
				if ($p == self::B_PARA && !$break) {
					continue 2;
				}
				if ($p == self::B_LIST) {
					$out .= "<li>";
					$out .= $this->HandleBlock( 
						array_slice( $lines, $start, $i - $start ),
						$file 
					);
					$start = $i;
					continue 2;
				}
				break;
			}

			// Previous block ended with this line
			switch ($block) {
			case self::B_PRE:
				$text = htmlspecialchars( $text );
				$out .= "<code><pre>" . $text . "</pre></code>";
				break;
			case self::B_PARA:
				$text = $this->HandleInlines( trim( $text, " \t" ) );
				//$text = trim( $text, " \t" );
				$out .= "<p>$text</p>";
				break;
			case self::B_LIST:
				$out .= "<li>";
				$out .= $this->HandleBlock( 
					array_slice( $lines, $start, $i - $start - $break ), 
					$file 
				);
				$out .= "</ul>";
				break;
			case self::B_QUOTE:
				$out .= "<blockquote>";
				$out .= $this->HandleBlock( 
					array_slice( $lines, $start, $i - $start - $break ), 
					$file 
				);
				$out .= "</blockquote>";
				break;
			case self::B_PRE:
				$out .= "</code></pre>";
				break;
			}

			// Fill in any white space
			if ($break > 1) {
				$out .= str_repeat( "<br>", $break - 1 );
			}
			$break = 0;

			// Start a new block
			switch ($p) {
			case self::B_LIST:
				$start = $i;
				$out .= "<ul>";
				$block = $p;
				break;

			case self::B_QUOTE:
				$start = $i;
				$block = $p;
				break;

			case self::B_PARA:
				$l = $lines[$i];
				$text = substr( $file, $l[0], $l[1] - $l[0] );
				$block = $p;
				break;

			case self::B_INDENT:
				$l = $lines[$i];
				$text = substr( $file, $l[0], $l[1] - $l[0] ) . "\n";
				$block = self::B_PRE;
				break;

			case self::B_EOF:
				break 2;

			default:
				$block = null;
			}
		}
		return $out;
	}

	/*
	=====================
	ParseFile
	=====================
	*/
	protected function ParseFile( $text )
	{
		$scan = 0;
		$lines = [];
		$ll = strlen( $text );
		// TODO: This could be written to parse blocks from a stream instead of
		// loading it all at once.
		for (;;) {
			$ln = strpos( $text, "\n", $scan );
			if ($ln === FALSE) {
				$lines[] = [ $scan, strlen( $text ) ];
				break;
			}
			$lines[] = [ $scan, $ln ];
			$scan = $ln + 1;
			if ($scan >= $ll) {
				break;
			}
		}
		return $this->HandleBlock( $lines, $text );
	}

	/*
	=====================
	text
	=====================
	*/
	public function text( $text )
	{
		return $this->ParseFile( $text );
	}
}


