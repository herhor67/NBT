<?php

namespace Nbt;

class DataHandler
{
	private $floatString = "\77\360\0\0\0\0\0\0";

	/**
	* Read a byte tag from the file.
	* getTAGByte(resource $fPtr) : int
	**/
	public function getTAGByte($fPtr)
	{
		return unpack('c', fread($fPtr, 1))[1];
	}

	/**
	* Write a byte tag to the file.
	* putTAGByte(resource $fPtr, int $byte) : bool
	**/
	public function putTAGByte($fPtr, $byte)
	{
		return is_int(fwrite($fPtr, pack('c', $byte)));
	}

	/**
	* Read a string from the file.
	* getTAGString(resource $fPtr) : string
	**/
	public function getTAGString($fPtr)
	{
		if (!$stringLength = $this->getTAGShort($fPtr))
			return '';
		return utf8_decode(fread($fPtr, $stringLength));
	}

	/**
	* Write a string to the file.
	* putTAGString(resource $fPtr, string $string) : bool
	**/
	public function putTAGString($fPtr, $string)
	{
		$value = utf8_encode($string);
		return $this->putTAGShort($fPtr, strlen($value)) && is_int(fwrite($fPtr, $value));
	}

	/**
	* Read a short int from the file.
	* getTAGShort(resource $fPtr) : int
	**/
	public function getTAGShort($fPtr)
	{
		return $this->unsignedToSigned(unpack('n', fread($fPtr, 2))[1], 16);
	}

	/**
	* Write a short int to the file.
	* putTAGShort(resource $fPtr, int $short) : bool
	**/
	public function putTAGShort($fPtr, $short)
	{
		return is_int(fwrite($fPtr, pack('n', $this->signedToUnsigned($short, 16))));
	}

	/**
	* Get an int from the file.
	* getTAGInt(resource $fPtr) : int
	**/
	public function getTAGInt($fPtr)
	{
		return $this->unsignedToSigned(unpack('N', fread($fPtr, 4))[1], 32);
	}

	/**
	* Write an integer to the file.
	* putTAGInt(resource $fPtr, int $int) : bool
	**/
	public function putTAGInt($fPtr, $int)
	{
		return is_int(fwrite($fPtr, pack('N', $this->signedToUnsigned($int, 32))));
	}

	/**
	* Read a long int from the file.
	* getTAGLong(resource $fPtr) : int
	**/
	public function getTAGLong($fPtr)
	{
		list(, $firstHalf, $secondHalf) = unpack('N*', fread($fPtr, 8));
		if ($this->is64bit())
		{
			// Workaround for PHP bug #47564 in 64-bit PHP<=5.2.9
			$firstHalf  &= 0xFFFFFFFF;
			$secondHalf &= 0xFFFFFFFF;
			$value = ($firstHalf << 32) | $secondHalf;
			$value = $this->unsignedToSigned($value, 64);
		}
		else
		{
			if (!extension_loaded('gmp'))
				trigger_error(
					'This file contains a 64-bit number and execution cannot continue. '
					.'Please install the GMP extension for 64-bit number handling.',
					E_USER_ERROR
				);
			// Fix values >= 2^31 (same fix as above, but this time because it's > PHP_INT_MAX)
			$firstHalf = gmp_and($firstHalf, '0xFFFFFFFF');
			$secondHalf = gmp_and($secondHalf, '0xFFFFFFFF');
			$value = gmp_add($secondHalf, gmp_mul($firstHalf, '4294967296'));
			if (gmp_cmp($value, gmp_pow(2, 63)) >= 0)
				$value = gmp_sub($value, gmp_pow(2, 64));
			$value = gmp_strval($value);
		}
		return $value;
	}

	/**
	* Write a long int to the file.
	* putTAGLong(resource $fPtr, int $long) : bool
	**/
	public function putTAGLong($fPtr, $long)
	{
		if ($this->is64bit())
		{
			$firstHalf  = $long >> 32;
			$secondHalf = $long & 0xFFFFFFFF;
			$wResult = is_int(fwrite($fPtr, pack('NN', $firstHalf, $secondHalf)));
		}
		else
		{
			if (!extension_loaded('gmp'))
				trigger_error(
					'This file contains a 64-bit number and execution cannot continue. '
					.'Please install the GMP extension for 64-bit number handling.',
					E_USER_ERROR
				);
			$quarters = [];
			// 32-bit longs seem to be too long for pack() on 32-bit machines. Split into 4x16-bit instead.
			$quarters[0] = gmp_div(gmp_and($long, '0xFFFF000000000000'), gmp_pow(2, 48));
			$quarters[1] = gmp_div(gmp_and($long, '0x0000FFFF00000000'), gmp_pow(2, 32));
			$quarters[2] = gmp_div(gmp_and($long, '0x00000000FFFF0000'), gmp_pow(2, 16));
			$quarters[3] = gmp_and($long, '0xFFFF');
			$wResult = is_int(fwrite($fPtr, pack('nnnn', gmp_intval($quarters[0]), gmp_intval($quarters[1]), gmp_intval($quarters[2]), gmp_intval($quarters[3]))));
		}
		return $wResult;
	}

	/**
	* Read a float from the file.
	* getTAGFloat(resource $fPtr) : float
	**/
	public function getTAGFloat($fPtr)
	{
		return $this->getTAGFloatDouble($fPtr, 'f', 4);
	}

	/**
	* Write a float to the file.
	* putTAGFloat(resource $fPtr, float $float) : bool
	**/
	public function putTAGFloat($fPtr, $float)
	{
		return $this->putTagFloatDouble($fPtr, $float, 'f');
	}

	/**
	* Read a double from the file.
	* getTAGDouble(resource $fPtr) : float
	**/
	public function getTAGDouble($fPtr)
	{
		return $this->getTAGFloatDouble($fPtr, 'd', 8);
	}

	/**
	* Write a double to the file.
	* putTAGDouble(resource $fPtr, float $double) : bool
	**/
	public function putTAGDouble($fPtr, $double)
	{
		return $this->putTagFloatDouble($fPtr, $double, 'd');
	}

	/**
	* Get a double or a float from the file.
	* getTAGFloatDouble(resource $fPtr, string $packType, int $bytes) : float
	**/
	private function getTAGFloatDouble($fPtr, $packType, $bytes)
	{
		list(, $value) = (pack('d', 1) == $this->floatString) ? unpack($packType, fread($fPtr, $bytes)) : unpack($packType, strrev(fread($fPtr, $bytes)));
		return $value;
	}

	/**
	* Write a double or a float to the file.
	* putTagFloatDouble(resource $fPtr, float $value, string $packType) : bool
	**/
	private function putTagFloatDouble($fPtr, $value, $packType)
	{
		return is_int(fwrite($fPtr, (pack('d', 1) == $this->floatString) ? pack($packType, $value) : strrev(pack($packType, $value))));
	}

	/**
	* Read an array of bytes from the file.
	* getTAGByteArray(resource $fPtr) : int[]
	**/
	public function getTAGByteArray($fPtr)
	{
		$arrayLength = $this->getTAGInt($fPtr);
		$values = [];
		for ($i = 0; $i < $arrayLength; ++$i)
			$values[] = $this->getTAGByte($fPtr);
		return $values;
	}

	/**
	* Write an array of bytes to the file.
	* putTAGByteArray(resource $fPtr, int[]  $array) : bool
	* putTAGByteArray(resource $fPtr, string $array) : bool
	**/
	public function putTAGByteArray($fPtr, $array)
	{
		if (is_string($array))
		{
			$result = $this->putTAGInt($fPtr, strlen($array));
			for ($i = 0; $i < strlen($array); $i++)
				$result &= $this->putTAGByte($fPtr, ord($array[$i]));
		}
		else
		{
			$result = $this->putTAGInt($fPtr, count($array));
			for ($i = 0; $i < count($array); ++$i)
				$result &= $this->putTAGByte($fPtr, $array[$i]);
		}
		return $result;
	}

	/**
	* Read an array of integers from the file.
	* getTAGIntArray(resource $fPtr) : int[]
	**/
	public function getTAGIntArray($fPtr)
	{
		$arrayLength = $this->getTAGInt($fPtr);
		$values = [];
		for ($i = 0; $i < $arrayLength; ++$i)
			$values[] = $this->getTAGInt($fPtr);
		return $values;
	}

	/**
	* Write an array of integers to the file.
	* tagByte(resource $fPtr, int[] $array) : bool
	**/
	public function putTAGIntArray($fPtr, $array)
	{
		$result = $this->putTAGInt($fPtr, count($array));
		for ($i = 0; $i < count($array); ++$i)
			$result &= $this->putTAGInt($fPtr, $array[$i]);
		return $result;
	}

	/**
	* Read an array of longs from the file.
	* getTAGLongArray(resource $fPtr) : int[]
	**/
	public function getTAGLongArray($fPtr)
	{
		$arrayLength = $this->getTAGInt($fPtr);
		$values = [];
		for ($i = 0; $i < $arrayLength; ++$i)
			$values[] = $this->getTAGLong($fPtr);
		return $values;
	}

	/**
	* Write an array of longs to the file.
	* tagByte(resource $fPtr, int[] $array) : bool
	**/
	public function putTAGLongArray($fPtr, $array)
	{
		$result = $this->putTAGInt($fPtr, count($array));
		for ($i = 0; $i < count($array); ++$i)
			$result &= $this->putTAGLong($fPtr, $array[$i]);
		return $result;
	}

	/**
	* Write an array to the file.
	* putTagArray(resource $fPtr, int $array, string $packType) : bool
	**/
//	private function putTagArray($fPtr, $array, $packType)    NOT USED
//	{
//		return $this->putTAGInt($fPtr, count($array)) && is_int(fwrite($fPtr, pack($packType.count($array), ...$array)));
//	}

	/**
	* Convert an unsigned int to signed, if required.
	* unsignedToSigned(int $value, int $size) : int
	**/
	private function unsignedToSigned($value, $size)
	{
		if ($value >= (int) pow(2, $size - 1))
			$value -= (int) pow(2, $size);
		return $value;
	}

	/**
	* Convert an unsigned int to signed, if required.
	* signedToUnsigned(int $value, int $size) : int
	**/
	private function signedToUnsigned($value, $size)
	{
		if ($value < 0)
			$value += (int) pow(2, $size);
		return $value;
	}

	/**
	* Check if we're on a 64 bit machine.
	* is64bit() : bool
	**/
	public function is64bit()
	{
		return (PHP_INT_SIZE >= 8);
	}
}
