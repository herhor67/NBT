<?php

namespace Nbt;

/**
* We're going to rejig the node to accept the values we need to associate with a node.
**/
class Node implements \Tree\Node\NodeInterface
{
	use \Tree\Node\NodeTrait
	{
		__construct as private __traitConstruct;
	}

	/**
	* Create a node, optionally passing an array of children.
	* __construct([array $children])
	**/
	public function __construct($children = [])
	{
		$this->__traitConstruct(null, $children);
		$this->value = [];
	}

	/**
	* Set the type of this node.
	* setType(int $type) : this
	**/
	public function setType($type)
	{
		$this->value['type'] = $type;
		return $this;
	}

	/**
	* Set the name for this node.
	* setName(string $name) : this
	**/
	public function setName($name)
	{
		$this->value['name'] = $name;
		return $this;
	}

	/**
	* Set the value for this node.
	* tagByte(mixed $value) : this
	**/
	public function setValue($value)
	{
		$this->value['value'] = $value;
		return $this;
	}

	/**
	* Set the payload type for this node.
	* setPayloadType(int $type) : this
	**/
	public function setPayloadType($type)
	{
		$this->value['payloadType'] = $type;
		return $this;
	}

	/**
	* Get the type of this node.
	* tagByte() : int
	**/
	public function getType()
	{
		return $this->getKey('type');
	}

	/**
	* Get the name of this node.
	* getName() : string
	**/
	public function getName()
	{
		return $this->getKey('name');
	}

	/**
	* Get the value for this node.
	* getValue() : mixed
	**/
	public function getValue()
	{
		return $this->getKey('value');
	}

	/**
	* Get the payload type associated with this node.
	* getPayloadType() : int
	**/
	public function getPayloadType()
	{
		return $this->getKey('payloadType');
	}

	/**
	* Set a key for the current node.
	* setKey(string $key, mixed $value) : void
	**/
	public function setKey($key, $value)
	{
		$this->value[$key] = $value;
	}

	/**
	* Get a key for the current node.
	* getKey(string $key) : mixed
	**/
	public function getKey($key)
	{
		if (array_key_exists($key, $this->value))
			return $this->value[$key];
	}

	/**
	* Strip data out of this node to make a list payload (no name or type required).
	* makeListPayload() : this
	**/
	public function makeListPayload()
	{
		if (array_key_exists('name', $this->value))
			unset($this->value['name']);
		if (array_key_exists('type', $this->value))
			unset($this->value['type']);
		return $this;
	}

	/**
	* Find a child tag by name.
	* tagByte(string $name) : \Nbt\Node|false
	**/
	public function findChildByName($name)
	{
		if ($this->getName() == $name)
			return $this;
		// A list of Compound tags has no data associated with it, so just check for children.
		if (!$this->isLeaf())
			foreach ($this->getChildren() as $childNode)
			{
				$node = $childNode->findChildByName($name);
				if ($node)
					return $node;
			}
		return false;
	}
}
