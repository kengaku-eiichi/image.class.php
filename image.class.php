<?php

class image
{
	function __construct($resource = null, $mimetype = null)
	{
		$this->resource = $resource;
		$this->mimetype = $mimetype;
		if ($resource !== null)
		{
			switch ($mimetype)
			{
				case image_type_to_mime_type(IMAGETYPE_PNG):
					break;
				case image_type_to_mime_type(IMAGETYPE_GIF):
					imagetruecolortopalette($resource, false, 256);
					break;
			}
		}
	}

	function __destruct()
	{
		if (isset($this->resource)) imagedestroy($this->resource);
	}

	function __clone()
	{
		$resource = imageistruecolor($this->resource) ? imagecreatetruecolor($this->width(), $this->height()) : imagecreate($this->width(), $this->height());
		switch ($this->mimetype)
		{
			case image_type_to_mime_type(IMAGETYPE_PNG):
				break;
			case image_type_to_mime_type(IMAGETYPE_GIF):
				imagetruecolortopalette($resource, false, 256);
				break;
		}
		imagealphablending($resource, false);
		imagecopy($resource, $this->resource, 0, 0, 0, 0, $this->width(), $this->height());
		$this->resource = $resource;
	}

	function __toString()
	{
		if (!isset($this->toString))
		{
			$obLevel = ob_get_level();
			$temp = ob_get_clean();
			ob_start();
			switch ($this->mimetype)
			{
				case image_type_to_mime_type(IMAGETYPE_JPEG):
					imagejpeg($this->resource);
					break;
				case image_type_to_mime_type(IMAGETYPE_PNG):
					imagepng($this->resource);
					break;
				case image_type_to_mime_type(IMAGETYPE_GIF):
					imagegif($this->resource);
					break;
				default:
					return $this->fromString;
			}
			$this->toString = ob_get_clean();
			if ($obLevel)
			{
				ob_start();
				echo $temp;
			}
		}

		return $this->toString;
	}

	function toString()
	{
		return $this->__toString();
	}

	static function fromString($fromString, $mimetype = null)
	{
		if (($resource = @imagecreatefromstring($fromString)) === false) return false;
		$self = new self($resource);
		$self->fromString = $fromString;
		$self->gd_imagesize = getimagesizefromstring($fromString);
		$self->mimetype = $mimetype ? $mimetype : image_type_to_mime_type($self->gd_imagesize[2]);
		return $self;
	}
	static function fromJpeg($path)
	{
		return new self(imagecreatefromjpeg($path), image_type_to_mime_type(IMAGETYPE_JPEG));
	}
	static function fromPng($path)
	{
		return new self(imagecreatefrompng($path), image_type_to_mime_type(IMAGETYPE_PNG));
	}
	static function fromGif($path)
	{
		return new self(imagecreatefromgif($path), image_type_to_mime_type(IMAGETYPE_GIF));
	}

	function size()
	{
		return strlen("{$this}");
	}

	function width()
	{
		return imagesx($this->resource);
	}

	function height()
	{
		return imagesy($this->resource);
	}

	function rotate($angle)
	{
		if (!$angle) return $this;

		$transparent = imagecolortransparent($this->resource);
		imagealphablending($this->resource, false);
		$image = new self(imagerotate($this->resource, 360 - $angle, 0), $this->mimetype);
		if ($transparent < 0) imagesavealpha($image->resource, true);
		else imagecolortransparent($image->resource, $transparent);
		return $image;
	}

	function trimming($params)
	{
		if (!is_array($params)) throw new Exception(__METHOD__ . '() failed.');

		$width = $this->width();
		$height = $this->height();
		foreach ($params as $key => $value)
		{
			if ($value < 0) throw new Exception(__METHOD__ . '() failed.');
			switch ($key)
			{
				case 'top':
				case 'bottom':
					$height -= $value;
					break;
				case 'left':
				case 'right':
					$width -= $value;
					break;
				default:
					throw new Exception(__METHOD__ . '() failed.');
			}
		}
		if ($width === $this->width() && $height === $this->height()) return $this;
		if ($width < 0 || $height < 0) throw new Exception(__METHOD__ . '() failed.');

		$transparent = imagecolortransparent($this->resource);

		$image = new self(imageistruecolor($this->resource) ? imagecreatetruecolor($width, $height) : imagecreate($width, $height), $this->mimetype);
		imagealphablending($image->resource, false);
		imagecopy($image->resource, $this->resource, 0, 0, array_key_exists('left', $params) ? $params['left'] : 0, array_key_exists('top', $params) ? $params['top'] : 0, $width, $height);

		if ($transparent < 0) imagesavealpha($image->resource, true);
		else imagecolortransparent($image->resource, $transparent);

		return $image;
	}

	function rescale($scale)
	{
		if ($scale >= 1) throw new Exception(__METHOD__ . '() failed.');
		if ($scale <= 0) throw new Exception(__METHOD__ . '() failed.');

		$width = (int)round($this->width() * $scale);
		$height = (int)round($this->height() * $scale);

		if ($width === $this->width() && $height === $this->height()) return $this;

		$transparent = imagecolortransparent($this->resource);

		$image = new self(imageistruecolor($this->resource) ? imagecreatetruecolor($width, $height) : imagecreate($width, $height), $this->mimetype);
		imagealphablending($image->resource, false);
		switch ($image->mimetype)
		{
			case image_type_to_mime_type(IMAGETYPE_PNG):
			case image_type_to_mime_type(IMAGETYPE_GIF):
				imagecopyresized($image->resource, $this->resource, 0, 0, round(($this->width() - $width / $scale) / 2), round(($this->height() - $height / $scale) / 2), $width, $height, round($width / $scale), round($height / $scale));
				break;
			default:
				imagecopyresampled($image->resource, $this->resource, 0, 0, round(($this->width() - $width / $scale) / 2), round(($this->height() - $height / $scale) / 2), $width, $height, round($width / $scale), round($height / $scale));
		}

		if ($transparent < 0) imagesavealpha($image->resource, true);
		else imagecolortransparent($image->resource, $transparent);

		return $image;
	}

	function resize($size)
	{
		$width = array_key_exists('width', $size) ? $size['width'] : $this->width();
		$height = array_key_exists('height', $size) ? $size['height'] : $this->height();

		if (!array_key_exists('width', $size)) $width = (int)round($width * $height / $this->height());
		if (!array_key_exists('height', $size)) $height = (int)round($height * $width / $this->width());

		if ($width === $this->width() && $height === $this->height()) return $this;
		if ($width < 0 || $height < 0) throw new Exception(__METHOD__ . '() failed.');

		$transparent = imagecolortransparent($this->resource);

		$scale = $width / $this->width() > $height / $this->height() ? $width / $this->width() : $height / $this->height();
		$image = new self(imageistruecolor($this->resource) ? imagecreatetruecolor($width, $height) : imagecreate($width, $height), $this->mimetype);
		imagealphablending($image->resource, false);
		switch ($image->mimetype)
		{
			case image_type_to_mime_type(IMAGETYPE_PNG):
			case image_type_to_mime_type(IMAGETYPE_GIF):
				imagecopyresized($image->resource, $this->resource, 0, 0, round(($this->width() - $width / $scale) / 2), round(($this->height() - $height / $scale) / 2), $width, $height, round($width / $scale), round($height / $scale));
				break;
			default:
				imagecopyresampled($image->resource, $this->resource, 0, 0, round(($this->width() - $width / $scale) / 2), round(($this->height() - $height / $scale) / 2), $width, $height, round($width / $scale), round($height / $scale));
		}

		if ($transparent < 0) imagesavealpha($image->resource, true);
		else imagecolortransparent($image->resource, $transparent);

		return $image;
	}
}
