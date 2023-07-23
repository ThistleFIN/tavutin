<?php

/**
 * Class Tavuttaja
 *
 * This class processes text content and adds soft hyphenation tags for Finnish text.
 */
class Tavuttaja
{
    private static ?self $instance = null;
    private string $hyphen = '&shy;';
    private string $placeholder = '[[SHY]]';
    private string $regex_pattern_start;
    private string $regex_pattern_end;
    private array $vowels = ['a', 'e', 'i', 'o', 'u', 'y', 'å', 'ä', 'ö'];
    private array $consonants = ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'z'];
    private DOMDocument $dom;
    
    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct()
    {
        $this->regex_pattern_start = '/[' . implode('', $this->consonants) . '][' . implode('', $this->vowels) . ']/i';
        $this->regex_pattern_end = '/[' . implode('', $this->consonants) . implode('', $this->vowels) . ']{2}$/i';
        $this->dom = new DOMDocument();
    }
    
    /**
     * Returns the singleton instance of the Tavuttaja class.
     *
     * @return self The singleton instance of the Tavuttaja class.
     */
    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Adds shy tags to the given content and returns the processed content.
     *
     * @param string $content The content to process.
     * @return string The processed content.
     */
    public function add_shy_tags(string $content): string
    {
        $containsHtml = $content !== strip_tags($content);
        
        if ($containsHtml) {
            $processed_content = $this->process_html_content($content);
        } else {
            $processed_content = $this->hyphenate($content);
        }
    
        return strtr($processed_content, [$this->placeholder => $this->hyphen]);
    }
    
    /**
     * Processes the given HTML content and adds shy tags.
     *
     * @param string $content The HTML content to process.
     * @return string The processed content.
     */
    private function process_html_content(string $content): string
    {
        @$this->dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        
        $process_text_nodes = function (DOMNode $node) use (&$process_text_nodes) {
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $childNode) {
                    $process_text_nodes($childNode);
                }
            } elseif ($node->nodeType === XML_TEXT_NODE) {
                $this->process_text_node($node);
            }
        };
        
        $process_text_nodes($this->dom->documentElement);
        
        $processed_content = $this->dom->saveHTML();
    
        return preg_replace(['/(?<=^.{0})<!DOCTYPE.*?>/', '/<\/?html>/', '/<\/?body>/'], '', $processed_content);
    }
    
    /**
     * Processes the given text node and adds shy tags to its content.
     *
     * @param DOMNode $node The text node to process.
     */
    private function process_text_node(DOMNode $node): void
    {
        $inside_shortcode = preg_match('/\[[a-zA-Z0-9_]+/', $node->nodeValue);
        
        if (!$inside_shortcode) {
            $add_space_to_end = str_ends_with($node->nodeValue, ' ');
            $add_space_to_begin = str_starts_with($node->nodeValue, ' ');
            $words = preg_split('/\s+/', $node->nodeValue, -1, PREG_SPLIT_NO_EMPTY);
            $hyphenated_words = array_map([$this, 'hyphenate'], $words);
            $node_value = implode(' ', $hyphenated_words);
            
            if ($add_space_to_begin) {
                $node_value = ' ' . $node_value;
            }
            if ($add_space_to_end) {
                $node_value .= ' ';
            }
            
            $node->nodeValue = $node_value;
        }
    }
    
    /**
     * Hyphenates the given word by adding shy tags.
     *
     * @param string $word The word to hyphenate.
     * @return string The hyphenated word.
     */
    private function hyphenate(string $word): string
    {
        $hyphenated = $word;
        $hyphen_positions = $this->get_hyphen_positions($word);
        
        $count = 0;
        foreach ($hyphen_positions as $position) {
            $tried_hyphenation = $this->add_hyphen($hyphenated, $position + $count * mb_strlen($this->placeholder));
            if ($tried_hyphenation !== false) {
                $count++;
                $hyphenated = $tried_hyphenation;
            }
        }
        
        return $hyphenated;
    }
    
    /**
     * Returns an array of hyphenation positions for the given word.
     *
     * @param string $word The word to get hyphenation positions for.
     * @return array An array of hyphenation positions.
     */
    private function get_hyphen_positions(string $word): array
    {
        $hyphen_positions = [];
        preg_match_all($this->regex_pattern_start, $word, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $hyphen_positions[] = $match[1];
        }
        
        return $hyphen_positions;
    }
    
    /**
     * Attempts to add a hyphen to the given word at the specified index.
     * Returns the hyphenated word if successful, false otherwise.
     *
     * @param string $word The word to add a hyphen to.
     * @param int $idx The index at which to add the hyphen.
     * @return false|string The hyphenated word if successful, false otherwise.
     */
    private function add_hyphen(string $word, int $idx)
    {
        $first_part = mb_substr($word, 0, $idx);
        preg_match($this->regex_pattern_end, $first_part, $match);
        if (!empty($match)) {
            return mb_substr($word, 0, $idx) . $this->placeholder . mb_substr($word, $idx);
        } else {
            return false;
        }
    }
}