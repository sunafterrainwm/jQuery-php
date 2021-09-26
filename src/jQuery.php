<?php

namespace jQuery;
use \PHPHtmlParser\Dom;
use \PHPHtmlParser\Content;
use \PHPHtmlParser\Options;

class jQuery
{
	/** @var Dom\Parser */
	private static $parser = null;

    /**
     * Contains the nodes of this jQuery tree.
     *
     * @var Dom\Node\HtmlNode[]
     */
    private $nodes;

	/**
     * A global options array to be used by all load calls.
     *
     * @var ?Options
     */
    private $globalOptions;

    /**
     * The charset we would like the output to be in.
     *
     * @var string
     */
    private $defaultCharset = "UTF-8";


	private function __construct( Options $options = null )
	{
		$this->globalOptions = $options;
	}

	/**
	 * jQuery.fn.init
	 */
	public static function init( string $selector, Dom\Node\AbstractNode|jQuery $node, ?Options $options = null ): self
	{
		$jq = $node instanceof jQuery ? $node : self::fromNode( $node );
		$result = $jq->find( $selector );
		$result->setOptions( $options );
		return $result;
	}

	public static function fromString( string $html, ?Options $options = null ): self
	{
		if ( !self::$parser ) {
			self::$parser = new Dom\Parser();
		}
		return self::parseHtml( $html, $options );
	}

	public static function fromNode( Dom\Node\AbstractNode $node, ?Options $options = null ): self
	{
		return self::fromNodes( [ $node ], $options );
	}

	/**
	 * @param Dom\Node\AbstractNode[] $nodes
	 */
	public static function fromNodes( $nodes, ?Options $options = null ): self
	{
		$self = new self( $options );
		$self->nodes = $nodes;

		return $self;
	}

	private static function parseHtml( string $html, ?Options $options = null ): self
	{
		$localOptions = new Options();
        if ( $options !== null ) {
            $localOptions = $localOptions->setFromOptions( $options );
        }

		$self = new self( $options );

        $html = $self->domCleaner->clean( $html, $localOptions, $self->defaultCharset );

        $self->content = new Content( $html );

		/**
		 * @var Dom\Node\HTMLNode $root
		 */
        $root = self::$parser->parse( $localOptions, $self->content, \strlen( $html ) );
        self::$parser->detectCharset( $localOptions, $self->defaultCharset, $root );
	
		$self->nodes = [ $root->getChildren() ];

		
		return $self;
	}

    /**
     * Returns the inner html of the root node.
     *
     * @throws ChildNotFoundException
     * @throws UnknownChildTypeException
     * @throws NotLoadedException
     */
    public function __toString(): string
    {
        return $this->html();
    }

	public function __get( $name )
	{
		switch ($name) {
			case 'html':
				return $this->html();
				break;
			case 'text':
				return $this->text();
				break;
			case 'node':
				return $this->nodes;
				break;
		}
	}

    /**
     * Sets a global options array to be used by all load calls.
     */
    public function setOptions( Options $options ): self
    {
        $this->globalOptions = $options;

        return $this;
    }

	/**
	 * jQuery.fn.get
	 * 
	 * @param int $i
	 * @return Dom\Node\AbstractNode[]|Dom\Node\AbstractNode
	 */
	public function get( int $i = null ): array|Dom\Node\AbstractNode
	{
		$children = $this->root->getChildren();
		return $i ? $children[ $i ] : $children;
	}

	/**
	 * jQuery.fn.clone
	 */
	public function clone(): self
	{
		return self::fromString( $this->__toString() );
	}

	/**
	 * jQuery.fn.remove
	 */
	public function remove( string $selector = null ): self
	{
		if ( $selector ) {
			$this->find( $selector )->remove();
		} else {
			foreach ( $this->nodes as $ele ) {
				$ele->delete();
			}
		}
		return $this;
	}

	/**
	 * jQuery.fn.empty
	 */
	public function empty(): self
	{
		foreach ( $this->children()->nodes as $ele ) {
			$ele->delete();
		}

		return $this;
	}

	private function getRoot(): Dom\Node\HtmlNode
	{
		$root = new Dom\Node\HtmlNode( "root" );
		foreach ( $this->clone()->nodes as $ele ) {
			$root->addChild( $ele );
		}
		return $root;
	}

	/**
	 * jQuery.fn.text
	 */
	public function text( string $text = null ): string|self
	{
		if ( $text ) {
			$this->empty();
			$this->append( new Dom\Node\TextNode( $text ) );
			return $this;
		} else {
			return $this->getRoot()->text();
		}
	}

	/**
	 * jQuery.fn.html
	 */
	public function html( string $html = null ): string|self
	{
		if ( $html ) {
			$this->empty();
			$this->append( $html );
			return $this;
		} else {
			return $this->getRoot()->outerHtml();
		}
	}

	/**
	 * jQuery.fn.find
	 */
	public function find( string $selector )
	{
		/**
		 * @var Dom\Node\HTMLNode[] $nodes
		 */
		$nodes = [];
	
		foreach ( $this->nodes as $ele ) {
			$n = $ele->find( $selector );
			if ( $n ) {
				$nodes = array_merge( $nodes, (array)$n );
			}
		}

		return self::fromNodes( $nodes );
	}

	/**
	 * jQuery.fn.append
	 */
	public function append( Dom\Node\AbstractNode|string ...$eles ): self
	{
		/**
		 * @var \jQuery\jQuery[] $jq
		 */
		$jq = [];

		foreach ( $eles as $ele ) {
			if ( gettype( $ele ) === gettype( "string" ) ) {
				$jq[] = self::fromString( $ele, $this->globalOptions );
			} else {
				$jq[] = self::fromNode( $ele, $this->globalOptions );
			}
		}

		foreach ( $jq as $j ) {
			foreach ( $this->nodes as $node ) {
				foreach ( $j->clone()->nodes as $ele ) {
					$node->addChild( $ele );
				}
			}
		}

		return $this;
	}

	/**
	 * jQuery.fn.children
	 */
	public function children(): self
	{
		/**
		 * @var Dom\Node\HTMLNode[] $nodes
		 */
		$children = [];
	
		foreach ( $this->nodes as $ele ) {
			$n = $ele->getChildren();
			if ( $n ) {
				$children = array_merge( $children, (array)$n );
			}
		}

		return self::fromNodes( $children );
	}

	/**
	 * jQuery.fn.filter
	 * 
	 * key: int
	 * 
	 * value: \PHPHtmlParser\Dom\Node\HTMLNode
	 */
	public function filter( callable $callback, int $filiterMode ): self
	{
		/**
		 * @var Dom\Node\HTMLNode[] $nodes
		 */
		$j = $this->clone();

		$j->nodes = array_filter( $j->nodes, $callback, $filiterMode );

		return $j;
	}
}