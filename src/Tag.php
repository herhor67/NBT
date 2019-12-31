<?php

namespace Nbt;

class Tag
{
	const TAG_END        = 0;  // End of compound
	const TAG_BYTE       = 1;  // Signed byte (8 bit)
	const TAG_SHORT      = 2;  // Signed short (16 bit, big endian)
	const TAG_INT        = 3;  // Signed integer (32 bit, big endian)
	const TAG_LONG       = 4;  // Signed long (64 bit, big endian)
	const TAG_FLOAT      = 5;  // Floating point value (32 bit, big endian, IEEE 754-2008)
	const TAG_DOUBLE     = 6;  // Double value (64 bit, big endian, IEEE 754-2008)
	const TAG_BYTE_ARRAY = 7;  // Byte array
	const TAG_STRING     = 8;  // String
	const TAG_LIST       = 9;  // List
	const TAG_COMPOUND   = 10; // Compound
	const TAG_INT_ARRAY  = 11; // Int array
	const TAG_LONG_ARRAY = 12; // Long array

	/**
	* Get a TAG_BYTE node.
	* tagByte(string $name, int $value) : Node
	**/
	public static function tagByte($name, $value)
	{
		return self::simpleTag(self::TAG_BYTE, $name, $value);
	}

	/**
	* Get a TAG_SHORT node.
	* tagShort(string $name, int $value) : Node
	**/
	public static function tagShort($name, $value)
	{
		return self::simpleTag(self::TAG_SHORT, $name, $value);
	}

	/**
	* Get a TAG_INT node.
	* tagInt(string $name, int $value) : Node
	**/
	public static function tagInt($name, $value)
	{
		return self::simpleTag(self::TAG_INT, $name, $value);
	}

	/**
	* Get a TAG_LONG node.
	* tagLong(string $name, int $value) : Node
	**/
	public static function tagLong($name, $value)
	{
		return self::simpleTag(self::TAG_LONG, $name, $value);
	}

	/**
	* Get a TAG_FLOAT node.
	* tagFloat(string $name, float $value) : Node
	**/
	public static function tagFloat($name, $value)
	{
		return self::simpleTag(self::TAG_FLOAT, $name, $value);
	}

	/**
	* Get a TAG_DOUBLE node.
	* tagDouble(string $name, float $value) : Node
	**/
	public static function tagDouble($name, $value)
	{
		return self::simpleTag(self::TAG_DOUBLE, $name, $value);
	}

	/**
	* Get a TAG_BYTE_ARRAY node.
	* tagByteArray(string $name, int[] $value) : Node
	**/
	public static function tagByteArray($name, $value)
	{
		return self::simpleTag(self::TAG_BYTE_ARRAY, $name, $value);
	}

	/**
	* Get a TAG_STRING node.
	* tagString(string $name, string $value) : Node
	**/
	public static function tagString($name, $value)
	{
		return self::simpleTag(self::TAG_STRING, $name, $value);
	}

	/**
	* Get a TAG_INT_ARRAY node.
	* tagIntArray(string $name, int[] $value) : Node
	**/
	public static function tagIntArray($name, $value)
	{
		return self::simpleTag(self::TAG_INT_ARRAY, $name, $value);
	}

	/**
	* Get a TAG_LIST node.
	* tagList(string $name, int $payloadType, Node[] $payload) : Node
	**/
	public static function tagList($name, $payloadType, $payload)
	{
		$node = (new Node())->setType(self::TAG_LIST)->setName($name)->setPayloadType($payloadType);
		foreach ($payload as $child)
			$node->addChild($child->makeListPayload());
		return $node;
	}

	/**
	* Get a TAG_COMPOUND node.
	* tagByte(string $name, Node[] $nodes) : Node
	**/
	public static function tagCompound($name, $nodes)
	{
		$node = (new Node())->setType(self::TAG_COMPOUND)->setName($name);
		foreach ($nodes as $child)
			$node->addChild($child);
		return $node;
	}

	/**
	* Get a simple tag with a value.
	* simpleTag(int $type, string $name, mixed $value) : Node
	**/
	private static function simpleTag($type, $name, $value)
	{
		return (new Node())->setType($type)->setName($name)->setValue($value);
	}
}
