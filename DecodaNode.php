<?php

class DecodaNode {
	
	/**
	 * Extracted chunks of text and tags.
	 * 
	 * @access protected
	 * @var array
	 */
	protected $_chunks = array();
	
	/**
	 * Children nodes.
	 * 
	 * @access protected
	 * @var array
	 */
	protected $_nodes = array();

	/**
	 * The parsed string.
	 * 
	 * @access protected
	 * @var string
	 */
	protected $_parsed = '';
	
	/**
	 * The raw string before parsing.
	 * 
	 * @access protected
	 * @var string
	 */
	protected $_string = '';

	/**
	 * The parent tag if the node isn't purely text.
	 * 
	 * @access protected
	 * @var array
	 */
	protected $_tag = array();
	
	/**
	 * Parent Decoda object.
	 * 
	 * @access private
	 * @var Decoda
	 */
	private $__parser;
	
	/**
	 * Convert the extracted chunks into concatenated nodes. Nodes will be created in a parent child 
	 * hierarchy depending on the amount of nested tags.
	 * 
	 * @access public
	 * @param type $chunks
	 * @param Decoda $parser
	 * @return void
	 */
	public function __construct($chunks, Decoda $parser) {
		$this->__parser = $parser;
		
		if (is_string($chunks)) {
			$this->_string = $chunks;
			return;
		}
		
		// Validate the data
		if ($chunks[0]['type'] == Decoda::TAG_OPEN) {
			$this->_tag = $chunks[0];
		}
		
		$chunks = $this->_cleanChunks($chunks);

		// Generate child nodes
		$tag = array();
		$text = '';
		$openIndex = -1;
		$openCount = -1;
		$closeIndex = -1;
		$closeCount = -1;
				
		foreach ($chunks as $i => $chunk) {
			if ($chunk['type'] == Decoda::TAG_NONE && empty($tag)) {
				$this->_nodes[] = new DecodaNode($chunk['text'], $this->__parser);
				
			} else if ($chunk['type'] == Decoda::TAG_OPEN && $i != 0) {
				$openCount++;

				if (empty($tag)) {
					$openIndex = $i;
					$tag = $chunk;
				}
				
			} else if ($chunk['type'] == Decoda::TAG_CLOSE) {
				$closeCount++;

				if ($openCount == $closeCount && $chunk['tag'] == $tag['tag']) {
					$closeIndex = $i;
					$tag = array();

					// Slice a section of the array if the correct closing tag is found
					$this->_nodes[] = new DecodaNode(array_slice($chunks, $openIndex, ($closeIndex - $openIndex) + 1), $this->__parser);
				}
			}
			
			$text .= $chunk['text'];
		}
		
		$this->_chunks = $chunks;
		$this->_string = $text;
	}

	/**
	 * Parse the node hierarchy by looping over all children nodes and parse using the respective filters.
	 * 
	 * @access public
	 * @return string
	 */
	public function parse() {
		if (!empty($this->_parsed)) {
			return $this->_parsed;
		}

		// No child nodes, return text
		if (empty($this->_nodes) || empty($this->_tag)) {
			$this->_parsed .= $this->_string;

		// Child nodes, validate and build tags
		} else {
			$filter = $this->__parser->filter($this->_tag['tag']);
			
			$this->_parsed .= $filter->openTag($this->_tag['tag'], $this->_tag['attributes']);
			
			foreach ($this->_nodes as $node) {
				$this->_parsed .= $node->parse();
			}

			$this->_parsed .= $filter->closeTag($this->_tag['tag']);
		}

		return $this->_parsed;
	}
	
	/**
	 * Begin the parsing process for the root node.
	 * 
	 * @access public
	 * @return string
	 */
	public function prepare() {
		if (!empty($this->_parsed)) {
			return $this->_parsed;
		}
		
		foreach ($this->_nodes as $node) {
			$this->_parsed .= $node->parse();
		}
		
		return $this->_parsed;
	}
	
	/**
	 * Clean the chunk list by verifying that open and closing tags are nested correctly.
	 * 
	 * @access protected
	 * @param array $chunks
	 * @return array 
	 */
	protected function _cleanChunks($chunks) {
		$clean = array();
		$openTags = array();
		$prevTag = array();
		$root = true;
		
		if (!empty($this->_tag['tag'])) {
			$filter = $this->__parser->filter($this->_tag['tag']);
			$parent = $filter->tag($this->_tag['tag']);
			$root = false;
		}
		
		foreach ($chunks as $i => $chunk) {
			$prevTag = end($clean);
			
			switch ($chunk['type']) {
				case Decoda::TAG_NONE:
					if ($prevTag['type'] === Decoda::TAG_NONE) {
						$chunk['text'] = $prevTag['text'] . $chunk['text'];
						array_pop($clean);
					}
					
					$clean[] = $chunk;
				break;

				case Decoda::TAG_OPEN:
					if ($root) {
						$clean[] = $chunk;
						$openTags[] = array('tag' => $chunk['tag'], 'index' => $i);
						
					} else if ($i != 0 && $this->_isAllowed($parent, $this->__parser->tag($chunk['tag']))) {
						$clean[] = $chunk;
					}
				break;
				
				case Decoda::TAG_CLOSE:
					if ($root) {
						if (empty($openTags)) {
							continue;
						}
						
						$last = end($openTags);
						
						if ($last['tag'] == $chunk['tag']) {
							array_pop($openTags);
						} else {
							while (!empty($openTags)) {
								$last = array_pop($openTags);
								
								if ($last['tag'] != $chunk['tag']) {
									unset($clean[$last['index']]);
								}
							}
						}
						
						$clean[] = $chunk;
						
					} else if ($i != (count($chunks) - 1) && $this->_isAllowed($parent, $this->__parser->tag($chunk['tag']))) {
						$clean[] = $chunk;
					}
				break;
			}
		}

		// Remove any unclosed tags
		while (!empty($openTags)) {
			$last = array_pop($openTags);
			unset($clean[$last['index']]);
		}
		
		return array_values($clean);
	}
	
	/**
	 * Validate that the following child can be nested within the parent.
	 * 
	 * @access protected
	 * @param array $parent
	 * @param array $child
	 * @return boolean 
	 */
	protected function _isAllowed($parent, $child) {
		if ($parent['allowed'] == 'all') {
			return true;
			
		} else if (($parent['allowed'] == 'inline' || $parent['allowed'] == 'block') && $child['type'] == 'inline') {
			return true;
		}
		
		return false;
	}
	
}