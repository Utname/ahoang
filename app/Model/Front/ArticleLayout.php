<?php

/**
 * Turns a text page written in the admin editor (paragraphs, <br> lines, bold lines, lists) into
 * blocks for the "Landwind" front: intro, text sections, feature cards ("Label: text" lines),
 * check lists, job cards (title + "Požadujeme:" / "Benefity:" groups) and contact buttons.
 * Text only – the markup of the editor is not kept (links come back as tel:/mailto: buttons).
 */
class ArticleLayout
{
	private const HEADING_MAX = 80;
	private const PHONE = '#(\+?\d[\d ]{7,}\d)#u';
	private const EMAIL = '#[\w.+-]+@[\w-]+(?:\.[\w-]+)+#u';


	/**
	 * @return array{intro: list<string>, blocks: list<array<string, mixed>>, contacts: array{phones: list<string>, emails: list<string>}}
	 */
	public static function build(string $html): array
	{
		$lines = self::lines($html);
		$intro = [];
		$sections = [];
		$current = NULL;
		$prevHeading = FALSE;

		foreach ($lines as $line) {
			$isHeading = self::isHeading($line);
			if ($isHeading && $current !== NULL && $prevHeading && $line['bold'] && !$current['subtitle'] && !self::hasContent($current)) {
				// a second bold line right under the title: "do prodejny potravin Fikko market Žatec"
				$current['subtitle'] = $line['text'];
				continue;
			}
			if ($isHeading) {
				if ($current !== NULL) {
					$sections[] = $current;
				}
				$current = ['title' => rtrim($line['text'], ': '), 'subtitle' => '', 'paras' => [], 'features' => [], 'list' => [], 'groups' => [], 'phones' => [], 'emails' => []];
				$prevHeading = TRUE;
				continue;
			}
			$prevHeading = FALSE;
			if ($current === NULL) {
				$intro[] = $line['text'];
				continue;
			}
			self::addLine($current, $line);
		}
		if ($current !== NULL) {
			$sections[] = $current;
		}

		// consecutive job sections become one card grid; contacts of the other sections end the page
		$blocks = [];
		$contacts = ['phones' => [], 'emails' => []];
		foreach ($sections as $section) {
			$type = match (TRUE) {
				(bool)$section['groups'] => 'job',
				count($section['features']) >= 2 => 'features',
				count($section['list']) >= 2 => 'list',
				(bool)($section['phones'] || $section['emails']) => 'contact',
				default => 'text',
			};
			if ($type !== 'job') {
				$contacts['phones'] = array_values(array_unique(array_merge($contacts['phones'], $section['phones'])));
				$contacts['emails'] = array_values(array_unique(array_merge($contacts['emails'], $section['emails'])));
			}
			if ($type === 'job' && $blocks && end($blocks)['type'] === 'jobs') {
				$blocks[count($blocks) - 1]['items'][] = $section;
				continue;
			}
			$blocks[] = $type === 'job' ? ['type' => 'jobs', 'items' => [$section]] : ['type' => $type] + $section;
		}

		return ['intro' => $intro, 'blocks' => $blocks, 'contacts' => $contacts];
	}


	/** tel: link for a number written in the text ("775 776 888" → +420775776888) */
	public static function tel(string $phone): string
	{
		$digits = preg_replace('#[^\d+]#', '', $phone);
		return strlen(ltrim($digits, '+')) === 9 && $digits[0] !== '+' ? '+420' . $digits : $digits;
	}


	/**
	 * Repairs phone / e-mail links typed wrongly in the editor ("http://mailto:x@y", "//775776888",
	 * "tel:+420 775 …") so they call or write instead of opening a broken page; no new tab for them.
	 * Blank paragraphs (only spaces / &nbsp; / <br>) are dropped: they left gaps in the branch cards.
	 */
	public static function fixLinks(?string $html): string
	{
		$html = preg_replace('#<p\b[^>]*>(?:\s|&nbsp;|&\#160;|\xC2\xA0|<br\s*/?>)*</p>#iu', '', (string)$html) ?? (string)$html;
		return preg_replace_callback('#<a\b([^>]*)>#i', static function (array $m): string {
			$attrs = $m[1];
			if (!preg_match('#\bhref\s*=\s*(["\'])(.*?)\1#is', $attrs, $h)) {
				return $m[0];
			}
			$href = trim(html_entity_decode($h[2]));
			$new = NULL;
			if (preg_match('#^(?:https?:)?(?://)?\s*mailto:\s*(.+)$#i', $href, $x)) {
				$new = 'mailto:' . trim($x[1]);
			}
			elseif (preg_match('#^(?:tel:|(?:https?:)?//)?\s*(\+?[\d ]{8,})/?$#i', $href, $x)) {
				$new = 'tel:' . self::tel($x[1]);
			}
			if ($new === NULL) {
				return $m[0];
			}
			$attrs = str_replace($h[0], 'href="' . htmlspecialchars($new, ENT_QUOTES) . '"', $attrs);
			$attrs = preg_replace('#\s*\btarget\s*=\s*(["\'])_blank\1#i', '', $attrs);
			return '<a' . $attrs . '>';
		}, (string)$html) ?? (string)$html;
	}


	/** True when the structure is worth the rich layout (otherwise the plain article is shown). */
	public static function isStructured(array $layout): bool
	{
		return count($layout['blocks']) >= 2 || ($layout['blocks'] && $layout['blocks'][0]['type'] !== 'text');
	}


	private static function hasContent(array $section): bool
	{
		return $section['paras'] || $section['features'] || $section['list'] || $section['groups'] || $section['phones'] || $section['emails'];
	}


	/** @param array{text: string, bold: bool, li: bool, single: bool, tag: string} $line */
	private static function isHeading(array $line): bool
	{
		$text = $line['text'];
		$len = mb_strlen($text);
		if ($line['li'] || self::isContact($text) || preg_match('#:\s*\S#u', $text) || $len > self::HEADING_MAX) {
			return FALSE;
		}
		if (str_ends_with($text, ':') && $len <= 40) {
			return FALSE; // "Požadujeme:" – a group label
		}
		// bold short lines, or a short paragraph of its own without a full stop
		return $line['bold'] || ($line['single'] && $len <= 60 && !preg_match('#[.]$#u', $text));
	}


	private static function isContact(string $text): bool
	{
		$rest = trim(preg_replace([self::PHONE, self::EMAIL], '', $text), " \t:-–");
		return (preg_match(self::PHONE, $text) || preg_match(self::EMAIL, $text)) && mb_strlen($rest) <= 45;
	}


	/** @param array<string, mixed> $section */
	private static function addLine(array &$section, array $line): void
	{
		$text = $line['text'];
		if (self::isContact($text)) {
			if (preg_match_all(self::PHONE, $text, $m)) {
				$section['phones'] = array_values(array_unique(array_merge($section['phones'], array_map('trim', $m[1]))));
			}
			if (preg_match_all(self::EMAIL, $text, $m)) {
				$section['emails'] = array_values(array_unique(array_merge($section['emails'], $m[0])));
			}
			return;
		}
		if (str_ends_with($text, ':') && mb_strlen($text) <= 40) {
			$section['groups'][] = ['label' => rtrim($text, ': '), 'items' => []];
			return;
		}
		if ($section['groups']) {
			$section['groups'][count($section['groups']) - 1]['items'][] = $text;
			return;
		}
		if (preg_match('#^([^:]{2,40}):\s+(.{12,})$#us', $text, $m)) {
			$section['features'][] = ['title' => trim($m[1]), 'text' => trim($m[2])];
			return;
		}
		if ($line['li']) {
			$section['list'][] = $text;
			return;
		}
		$section['paras'][] = $text;
	}


	/**
	 * Text lines of the editor HTML: every <br> and every block ends a line.
	 * @return list<array{text: string, bold: bool, li: bool, single: bool, tag: string}>
	 */
	private static function lines(string $html): array
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$prev = libxml_use_internal_errors(TRUE);
		$doc->loadHTML('<?xml encoding="UTF-8"?><div id="article-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		$root = $doc->getElementById('article-root');
		$out = [];
		if ($root) {
			self::walkBlocks($root, $out);
		}
		return $out;
	}


	private static function walkBlocks(DOMNode $node, array &$out): void
	{
		foreach ($node->childNodes as $child) {
			if (!$child instanceof DOMElement) {
				if (trim($child->textContent ?? '') !== '') {
					self::pushLines([[$child->textContent, 0]], $out, FALSE, 'text');
				}
				continue;
			}
			$tag = strtolower($child->tagName);
			if (in_array($tag, ['ul', 'ol', 'div', 'section', 'blockquote', 'table', 'tbody', 'tr'], TRUE)) {
				self::walkBlocks($child, $out);
				continue;
			}
			$parts = [];
			$segment = ['', 0];
			self::collect($child, $parts, $segment, preg_match('#^h[1-6]$#', $tag) === 1);
			$parts[] = $segment;
			self::pushLines($parts, $out, $tag === 'li' || $tag === 'td', $tag);
		}
	}


	/** Splits an element at <br> into [text, boldChars] segments. */
	private static function collect(DOMNode $node, array &$parts, array &$segment, bool $bold): void
	{
		foreach ($node->childNodes as $child) {
			if ($child instanceof DOMText) {
				$segment[0] .= $child->data;
				if ($bold) {
					$segment[1] += mb_strlen(preg_replace('#\s+#u', '', $child->data));
				}
				continue;
			}
			if (!$child instanceof DOMElement) {
				continue;
			}
			$tag = strtolower($child->tagName);
			if ($tag === 'br') {
				$parts[] = $segment;
				$segment = ['', 0];
				continue;
			}
			self::collect($child, $parts, $segment, $bold || in_array($tag, ['strong', 'b'], TRUE));
		}
	}


	private static function pushLines(array $parts, array &$out, bool $li, string $tag): void
	{
		$lines = [];
		foreach ($parts as [$text, $boldChars]) {
			$text = trim(preg_replace('#[\s\x{00A0}]+#u', ' ', $text));
			if ($text === '') {
				continue;
			}
			$chars = mb_strlen(preg_replace('#\s+#u', '', $text));
			$lines[] = ['text' => $text, 'bold' => $chars > 0 && $boldChars >= $chars * 0.9, 'li' => $li, 'single' => FALSE, 'tag' => $tag];
		}
		if (count($lines) === 1) {
			$lines[0]['single'] = TRUE;
		}
		array_push($out, ...$lines);
	}
}
