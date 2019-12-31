<?php

/**
 * Class for reading in NBT-format files.
 *
 * @author  Justin Martin <frozenfire@thefrozenfire.com>
 * @author  Rick Selby <rick@selby-family.co.uk>
 * @author  herhor67 <herhor67@interia.pl>
 */
namespace Nbt;

class Service
{
	/** @var \Nbt\DataHandler **/
	private $dataHandler;

	/**
	* Ready the class; check if longs will be a problem.
	**/
	public function __construct(DataHandler $dataHandler)
	{
		$this->dataHandler = $dataHandler;
		if (!$this->dataHandler->is64bit() && !extension_loaded('gmp'))
		{
			trigger_error(
				'The NBT class requires the GMP extension for 64-bit number handling on 32-bit PHP builds. '.
				'Execution will continue, but will halt if a 64-bit number is handled.',
				E_USER_NOTICE
			);
		}
	}

	/**
	* Load a file and read the NBT data from the file.
	* loadFile(string $filename[, string $wrapper]) : Node|false
	**/
	public function loadFile($filename, $wrapper = 'compress.zlib://')
	{
		if (is_file($filename))
		{
			$fPtr = fopen("{$wrapper}{$filename}", 'rb');
			return $this->readFilePointer($fPtr);
		}
		else
		{
			trigger_error('First parameter must be a filename.', E_USER_WARNING);
			return false;
		}
	}

	/**
	* Write the current NBT root data to a file.
	* writeFile(string $filename, Node $tree[, string $wrapper]) : bool
	**/
	public function writeFile($filename, Node $tree, $wrapper = 'compress.zlib://')
	{
		$fPtr = fopen("{$wrapper}{$filename}", 'wb');
		$success = $this->writeFilePointer($fPtr, $tree);
		fclose($fPtr);
		return $success;
	}

	/**
	* Read NBT data from the given file pointer.
	* readFilePointer(resource $fPtr) : Node|false
	**/
	public function readFilePointer($fPtr)
	{
		$treeRoot = new Node();
		$success = $this->traverseTag($fPtr, $treeRoot);
		if ($success)
			return $treeRoot;
		return false;
	}

	/**
	* Write the current NBT root data to the given file pointer.
	* writeFilePointer(resource $fPtr, Node $tree) : bool
	**/
	public function writeFilePointer($fPtr, Node $tree)
	{
		if (!$this->writeTag($fPtr, $tree))
		{
			trigger_error('Failed to write tree to file/resource.', E_USER_WARNING);
			return false;
		}
		return true;
	}

	/**
	* Read NBT data from a string.
	* readString(string $string) : Node|false
	**/
	public function readString($string)
	{
		$stream = fopen('php://memory', 'r+b');
		fwrite($stream, $string);
		rewind($stream);
		return $this->readFilePointer($stream);
	}

	/**
	* Get a string with the current NBT root data in NBT format.
	* writeString(Node $tree) : string
	**/
	public function writeString(Node $tree)
	{
		$stream = fopen('php://memory', 'r+b');
		$this->writeFilePointer($stream, $tree);
		rewind($stream);
		return stream_get_contents($stream);
	}

	/**
	* Read the next tag from the stream.
	* traverseTag(resource $fPtr, Node &$node) : bool
	**/
	private function traverseTag($fPtr, Node &$node)
	{
		if (feof($fPtr))
			return false;
		
		$tagType = $this->dataHandler->getTAGByte($fPtr);
		if ($tagType == Tag::TAG_END)
			return false;
		else
		{
			$node->setType($tagType);
			$tagName = $this->dataHandler->getTAGString($fPtr);
			$node->setName($tagName);
			$this->readType($fPtr, $tagType, $node);
			return true;
		}
	}

	/**
	* Write the given tag to the stream.
	* writeTag(resource $fPtr, Node $node) : bool
	**/
	private function writeTag($fPtr, Node $node)
	{
		return $this->dataHandler->putTAGByte($fPtr, $node->getType())
			&& $this->dataHandler->putTAGString($fPtr, $node->getName())
			&& $this->writeType($fPtr, $node->getType(), $node);
	}

	/**
	* Read an individual type from the stream.
	* tagByte(resource $fPtr, int $tagType, Node $node) : mixed
	**/
	private function readType($fPtr, $tagType, Node $node)
	{
		switch ($tagType)
		{
			case Tag::TAG_END:
				break;
			case Tag::TAG_BYTE:
				$node->setValue($this->dataHandler->getTAGByte($fPtr));
				break;
			case Tag::TAG_SHORT:
				$node->setValue($this->dataHandler->getTAGShort($fPtr));
				break;
			case Tag::TAG_INT:
				$node->setValue($this->dataHandler->getTAGInt($fPtr));
				break;
			case Tag::TAG_LONG:
				$node->setValue($this->dataHandler->getTAGLong($fPtr));
				break;
			case Tag::TAG_FLOAT:
				$node->setValue($this->dataHandler->getTAGFloat($fPtr));
				break;
			case Tag::TAG_DOUBLE:
				$node->setValue($this->dataHandler->getTAGDouble($fPtr));
				break;
			case Tag::TAG_BYTE_ARRAY:
				$node->setValue($this->dataHandler->getTAGByteArray($fPtr));
				break;
			case Tag::TAG_STRING:
				$node->setValue($this->dataHandler->getTAGString($fPtr));
				break;
			case Tag::TAG_LIST:
				$tagID = $this->dataHandler->getTAGByte($fPtr);
				$listLength = $this->dataHandler->getTAGInt($fPtr);
				$node->setPayloadType($tagID);
				for ($i = 0; $i < $listLength; ++$i)
				{
					if (feof($fPtr))
						break;
					$listNode = new Node();
					$this->readType($fPtr, $tagID, $listNode);
					$node->addChild($listNode);
				}
				break;
			case Tag::TAG_COMPOUND:
				$compoundNode = new Node();
				while ($this->traverseTag($fPtr, $compoundNode))
				{
					$node->addChild($compoundNode);
					$compoundNode = new Node(); // Reset the node for adding the next tags
				}
				break;
			case Tag::TAG_INT_ARRAY:
				$node->setValue($this->dataHandler->getTAGIntArray($fPtr));
				break;
			case Tag::TAG_LONG_ARRAY:
				$node->setValue($this->dataHandler->getTAGLongArray($fPtr));
				break;
			default:
				throw new \Exception('Unsupported tag type: '. $tagType);
				break;
		}
	}

	/**
	* Write an individual type to the stream.
	* writeType(resource $fPtr, int $tagType, Node $node) : bool
	**/
	private function writeType($fPtr, $tagType, Node $node)
	{
		switch ($tagType)
		{
			case Tag::TAG_END:
				return is_int(fwrite($fPtr, "\0"));
			case Tag::TAG_BYTE:
				return $this->dataHandler->putTAGByte($fPtr, $node->getValue());
			case Tag::TAG_SHORT:
				return $this->dataHandler->putTAGShort($fPtr, $node->getValue());
			case Tag::TAG_INT:
				return $this->dataHandler->putTAGInt($fPtr, $node->getValue());
			case Tag::TAG_LONG:
				return $this->dataHandler->putTAGLong($fPtr, $node->getValue());
			case Tag::TAG_FLOAT:
				return $this->dataHandler->putTAGFloat($fPtr, $node->getValue());
			case Tag::TAG_DOUBLE:
				return $this->dataHandler->putTAGDouble($fPtr, $node->getValue());
			case Tag::TAG_BYTE_ARRAY:
				return $this->dataHandler->putTAGByteArray($fPtr, $node->getValue());
			case Tag::TAG_STRING:
				return $this->dataHandler->putTAGString($fPtr, $node->getValue());
			case Tag::TAG_LIST:
				if (!$this->dataHandler->putTAGByte($fPtr, $node->getPayloadType()) || !$this->dataHandler->putTAGInt($fPtr, count($node->getChildren())))
					return false;
				foreach ($node->getChildren() as $childNode)
					if (!$this->writeType($fPtr, $node->getPayloadType(), $childNode))
						return false;
				return true;
			case Tag::TAG_COMPOUND:
				foreach ($node->getChildren() as $childNode)
					if (!$this->writeTag($fPtr, $childNode))
						return false;
				if (!$this->writeType($fPtr, Tag::TAG_END, new Node()))
					return false;
				return true;
			case Tag::TAG_INT_ARRAY:
				return $this->dataHandler->putTAGIntArray($fPtr, $node->getValue());
			case Tag::TAG_LONG_ARRAY:
				return $this->dataHandler->putTAGLongArray($fPtr, $node->getValue());
		}
	}
}
