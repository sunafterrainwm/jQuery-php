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
        return $this->root->innerHtml();
    }

    /**
     * Sets a global options array to be used by all load calls.
     */
    public function setOptions(Options $options): self
    {
        $this->globalOptions = $options;

        return $this;
    }

	/**
	 * @param int $i
	 * @return Dom\Node\AbstractNode[]|Dom\Node\AbstractNode
	 */
	public function get( int $i = null )
	{
		$children = $this->root->getChildren();
		return $i ? $children[ $i ] : $children;
	}

	public function clone()
	{
		return self::fromString( $this->__toString() );
	}

	public function remove( string $selector = null )
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

	public function empty()
	{
		foreach ( $this->children()->nodes as $ele ) {
			$ele->delete();
		}

		return $this;
	}

	private function getRoot() {
		$root = new Dom\Node\HtmlNode( "root" );
		foreach ( $this->clone()->nodes as $ele ) {
			$root->addChild( $ele );
		}
		return $root;
	}

	public function text( string $text = null )
	{
		if ( $text ) {
			$this->empty();
			$this->append( new Dom\Node\TextNode( $text ) );
			return $this;
		} else {
			return $this->getRoot()->text();
		}
	}

	public function html( string $html = null )
	{
		if ( $html ) {
			$this->empty();
			$this->append( $html );
			return $this;
		} else {
			return $this->getRoot()->outerHtml();
		}
	}

	public function find( string $selector ) {
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
	 * @param (Dom\Node\AbstractNode|string) $eles
	 */
	public function append( ...$eles )
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

	public function children()
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
}