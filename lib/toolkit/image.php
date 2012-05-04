<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

class Image
{
	public static function load($file, &$info)
	{
		if (!is_file($file))
		{
			throw new Exception('The file %file does not exists', array('%file' => $file));
		}

		$info = getimagesize($file);

		if (!$info)
		{
			throw new Exception('Unable to get information from file %file', array('%file' => $file));
		}

		$mime = $info['mime'];
		$image = false;

		switch ($mime)
		{
			case 'image/jpeg':
			{
				$image = imagecreatefromjpeg($file);
			}
			break;

			case 'image/png':
			{
				$image = imagecreatefrompng($file);
			}
			break;

			case 'image/gif':
			{
				$image = imagecreatefromgif($file);
			}
			break;

			default:
			{
				throw new Exception('Unsupported image type: %mime', array('%mime' => $mime));
			}
			break;
		}

		if (!$image)
		{
			Debug::trigger('Unable to create image from %file', array('%file' => $file));

			return false;
		}

		return $image;
	}

	const RESIZE_NONE = 'none';
	const RESIZE_FIT = 'fit';
	const RESIZE_FILL = 'fill';
	const RESIZE_FIXED_HEIGHT = 'fixed-height';
	const RESIZE_FIXED_HEIGHT_CROPPED = 'fixed-height-cropped';
	const RESIZE_FIXED_WIDTH = 'fixed-width';
	const RESIZE_FIXED_WIDTH_CROPPED = 'fixed-width-cropped';
	const RESIZE_SURFACE = 'surface';
	const RESIZE_SIMPLE = 'simple';
	const RESIZE_CONSTRAINED = 'constrained';

	public static function compute_final_size($w, $h, $method, $src)
	{
		static $same = array(self::RESIZE_FIT, self::RESIZE_FILL, self::RESIZE_FIXED_HEIGHT_CROPPED, self::RESIZE_FIXED_WIDTH_CROPPED);

		if (in_array($method, $same))
		{
			return array($w, $h);
		}

		list($image_w, $image_h) = getimagesize($src);

		$final_w = $w;
		$final_h = $h;

		switch ($method)
		{
			case self::RESIZE_FIXED_HEIGHT:
				break;
			case self::RESIZE_FIXED_WIDTH:
				break;

			case self::RESIZE_SURFACE:
			{
				$r = sqrt(($image_w * $image_h) / ($w * $h));

				$final_w = round($image_w / $r);
				$final_h = round($image_h / $r);
			}
			break;

			case self::RESIZE_CONSTRAINED:
			{
				$image_r = $image_w / $image_h;
				$r = $w / $h;

				$r = $image_r > $r ? $image_w / $w : $image_h / $h;

				$final_w = round($image_w / $r);
				$final_h = round($image_h / $r);

			}
			break;
		}

		return array($final_w, $final_h);
	}

	/**
	 * Resize a soure image and return its resized version.
	 *
	 * @param $source resource The source image.
	 * @param $t_w integer The desired width of the resized version. You need to provide a
	 * variable and not a value. If using the RESIZE_SURFACE method, the variable is set to
	 * the result width of the resized image.
	 * @param $t_h integer The desired height of the resized version. You need to provide a
	 * variable and not a value. If using the RESIZE_SURFACE method, the variable is set to
	 * the result height of the resized image.
	 * @param $method string One of the resize methods.
	 * @param $fill_callback callback An optionnal callback used to fill the resized image,
	 * before any pixel are actually copied.
	 *
	 * @return resource The resized image.
	 */
	public static function resize($source, &$t_w, &$t_h, $method, $fill_callback=null)
	{
		#
		# source dimensions
		#

		$s_x = 0;
		$s_y = 0;
		$s_w = imagesx($source);
		$s_h = imagesy($source);

		#
		# destination dimensions
		#

		$d_x = 0;
		$d_y = 0;
		$d_w = $t_w;
		$d_h = $t_h;

		#
		# select scale method
		#

		switch ($method)
		{
			case self::RESIZE_FIT:
			default:
			{
				#
				# fit
				#
				# Resize the image so that it all fits in the target space.
				# This will result in thumbnails equals in width and height, with a possible
				# background visible.
				#

				$s_r = $s_w / $s_h;
				$d_r = $d_w / $d_h;

				$r = $s_r > $d_r ? $s_w / $d_w : $s_h / $d_h;

				$d_w = round($s_w / $r);
				$d_h = round($s_h / $r);
			}
			break;

			case self::RESIZE_FILL:
			{
				#
				# fill
				#
				# Resize the image so that the whole target space is filled. The image is cropped
				# to the target width and height.
				# This will result in thumbnails equals in width and height, with the maximum of
				# information visible.
				#

				$s_r = $s_w / $s_h;
				$d_r = $d_w / $d_h;

				if ($s_r > $d_r)
				{
					$r = $s_h / $d_h;
					$s_x += round(($s_w - $t_w * $r) / 2);
				}
				else
				{
					$r = $s_w / $d_w;
					$s_y += round(($s_h - $t_h * $r) / 2);
				}

				$d_w = round($s_w / $r);
				$d_h = round($s_h / $r);
			}
			break;

			case self::RESIZE_FIXED_HEIGHT:
			{
				#
				# fixed-height
				#
				# The image is resized to match the target height.
				# The image width is resized accordingly.
				# This will result in thumbnails equals in height, but not in width.
				#

				$r = $s_h / $d_h;

				$d_w = round($s_w / $r);

				if ($s_w > $s_h)
				{
					$d_h = round($s_h / $r);
				}

				$t_w = $d_w;
			}
			break;

			case self::RESIZE_FIXED_HEIGHT_CROPPED:
			{
				#
				# fixed-height-cropped
				#
				# The image is resized to match the target height.
				# If the image width is larger than the target width, the image is cropped.
				# This will result in thumbnails equals in height, but not in width.
				#

				$r = $s_h / $d_h;

				$d_w = round($s_w / $r);

				if ($s_w > $s_h)
				{
					$d_h = round($s_h / $r);
				}
				else
				{
					$t_w = $d_w;
				}

				#
				# crop image
				#

				if ($s_w > $t_w * $r)
				{
					$s_x += round(($s_w - $t_w * $r) / 2);
				}
			}
			break;

			case self::RESIZE_FIXED_WIDTH:
			{
				#
				# fixed-width
				#
				# The image is resized to match the target width.
				# The image height resized accordingly.
				# This will result in thumbnails equals in width, but not in height.
				#

				$r = $s_w / $d_w;

				$d_h = round($s_h / $r);

				if ($s_w < $s_h)
				{
					$d_w = round($s_w / $r);
				}

				$t_h = $d_h;
			}
			break;

			case self::RESIZE_FIXED_WIDTH_CROPPED:
			{
				#
				# fixed-width-cropped
				#
				# The image is resized to match the target width.
				# If the image height is taller than the target height, the image is cropped.
				# This will result in thumbnails equals in width, but not in height.
				#

				$r = $s_w / $d_w;

				$d_h = round($s_h / $r);

				if ($s_w > $s_h)
				{
					$t_h = $d_h;
				}
				else
				{
					$d_w = round($s_w / $r);
				}

				#
				# crop image
				#

				if ($s_h > $t_h * $r)
				{
					$s_y += round(($s_h - $t_h * $r) / 2);
				}
			}
			break;

			case self::RESIZE_SURFACE:
			{
				#
				# surface
				#
				# The image is resized so to its surface matches the target surface.
				# this will result in thumbnails with different width and height, but with
				# the same amount of pixels.
				#

				$r = sqrt(($s_w * $s_h) / ($t_w * $t_h));

				$d_w = round($s_w / $r);
				$d_h = round($s_h / $r);

				$t_w = $d_w;
				$t_h = $d_h;

			}
			break;

			case self::RESIZE_SIMPLE:
			{
				$d_w = $t_w;
				$d_h = $t_h;
			}
			break;

			case self::RESIZE_CONSTRAINED:
			{
				$s_r = $s_w / $s_h;
				$d_r = $d_w / $d_h;

				$r = $s_r > $d_r ? $s_w / $d_w : $s_h / $d_h;

				$d_w = round($s_w / $r);
				$d_h = round($s_h / $r);

				$t_w = $d_w;
				$t_h = $d_h;
			}
			break;
		}

		#
		# center destination image result
		#

		if ($t_h > $d_h)
		{
			$d_y = round(($t_h - $d_h) / 2);
		}

		if ($t_w > $d_w)
		{
			$d_x = round(($t_w - $d_w) / 2);
		}

		#
		# create destination image
		#

		$destination = imagecreatetruecolor($t_w, $t_h);

		#
		# If the user didn't provide a callback to fill the background, the background is filled
		# with a transparent color. This might be usefull to resample images while preserving
		# their tranparency.
		#

		if ($fill_callback)
		{
			call_user_func($fill_callback, $destination, $t_w, $t_h);
		}
		else
		{
			$c = imagecolorallocatealpha($destination, 0, 0, 0, 127);

			imagefill($destination, 0, 0, $c);
		}

		#
		# now we resize and paste our image
		#

		imagecopyresampled
		(
			$destination,
			$source,

			$d_x, $d_y, $s_x, $s_y,
			$d_w, $d_h, $s_w, $s_h
		);

		return $destination;
	}

	/*
	**

	SUPPORT

	**
	*/

	static $grid_sizes = array
	(
		'none' => 0,
		'small' => 4,
		'medium' => 8,
		'large' => 16
	);

	static $grid_color_schemes = array
	(
		'light' => array(0xffffff, 0xcccccc),
		'medium' => array(0x999999, 0x666666),
		'dark' => array(0x333333, 0x666666),
		'red' => array(0xffffff, 0xffcccc),
		'orange' => array(0xffffff, 0xffd8bd),
		'green' => array(0xffffff, 0xcce4cc),
		'blue' => array(0xffffff, 0xcce0f8),
		'purple' => array(0xffffff, 0xdcccf8)
	);

	public static function draw_grid($image, $x1, $y1, $x2, $y2, $color1=0xFFFFFF, $color2=0xCCCCCC, $size=4)
	{
		#
		# resolve size
		#

		if (is_string($size) && !((int) $size))
		{
			$size = isset(self::$grid_sizes[$size]) ? self::$grid_sizes[$size] : 4;
		}

		#
		# resolve colors
		#

		if (is_string($color1) && isset(self::$grid_color_schemes[$color1]))
		{
			list($color1, $color2) = self::$grid_color_schemes[$color1];
		}

		#
		# allocate colors
		#

		$c1 = self::allocate_color($image, $color1);
		$c2 = self::allocate_color($image, $color2);

		#
		# draw grid
		#

		if ($size)
		{
			#
			# draw grid, line by line, square by square
			#

			for ($j = $y1, $b = 0 ; $j < $y2 ; $j += $size, $b++)
			{
				for ($i = $x1, $a = 0 ; $i < $x2 ; $i += $size, $a++)
				{
					imagefilledrectangle
					(
						$image,

						$i, $j,
						max($i + $size, $x2),
						max($j + $size, $y2),

						($a % 2) ? $c1 : $c2
					);
				}

				#
				# in order to have a nice pattern,
				# after each line is drawn we switch color 1 and color 2
				#

				$t = $c1;
				$c1 = $c2;
				$c2 = $t;
			}
		}
		else
		{
			#
			# grid size is 0, we simply draw a rectangle with color 1
			#

			imagefilledrectangle($image, $x1, $y1, $x2, $y2, $c1);
		}
	}

	/**
	 * Decode a color into an array of RGB values.
	 *
	 * @param $color mixed The color to decode.
	 * @return array The RGB value decoded as an array of components value
	 */

	public static function decode_color($color)
	{
		$len = is_string($color) ? strlen($color) : 0;

		if ($len >= 4)
		{
			if ($color[0] == '#')
			{
				switch ($len)
				{
					case 4:
					{
						return array
						(
							intval($color[1] . $color[1], 16),
							intval($color[2] . $color[2], 16),
							intval($color[3] . $color[3], 16)
						);
					}
					break;

					case 7:
					{
						return array
						(
							intval($color[1] . $color[2], 16),
							intval($color[3] . $color[4], 16),
							intval($color[5] . $color[6], 16)
						);
					}
					break;
				}
			}
			else if (isset(self::$color_names[$color]))
			{
				$color = self::$color_names[$color];
			}

			// TODO-20090418: add support for rgb()
		}

		if (is_array($color))
		{
			return $color;
		}
		else if (is_numeric($color))
		{
			return array
			(
				($color & 0xFF0000) >> 16, ($color & 0x00FF00) >> 8, ($color & 0x0000FF)
			);
		}

		#
		# decoding failed
		#

		return array(128, 128, 128);
	}

	public static function allocate_color($image, $color)
	{
		$color = self::decode_color($color);

		return imagecolorallocate
		(
			$image, $color[0], $color[1], $color[2]
		);
	}

	public static $color_names = array
	(
		'snow' => 0xFFFAFA,
		'snow1' => 0xFFFAFA,
		'snow2' => 0xEEE9E9,
		'RosyBrown1' => 0xFFC1C1,
		'RosyBrown2' => 0xEEB4B4,
		'snow3' => 0xCDC9C9,
		'LightCoral' => 0xF08080,
		'IndianRed1' => 0xFF6A6A,
		'RosyBrown3' => 0xCD9B9B,
		'IndianRed2' => 0xEE6363,
		'RosyBrown' => 0xBC8F8F,
		'brown1' => 0xFF4040,
		'firebrick1' => 0xFF3030,
		'brown2' => 0xEE3B3B,
		'IndianRed' => 0xCD5C5C,
		'IndianRed3' => 0xCD5555,
		'firebrick2' => 0xEE2C2C,
		'snow4' => 0x8B8989,
		'brown3' => 0xCD3333,
		'red' => 0xFF0000,
		'red1' => 0xFF0000,
		'RosyBrown4' => 0x8B6969,
		'firebrick3' => 0xCD2626,
		'red2' => 0xEE0000,
		'firebrick' => 0xB22222,
		'brown' => 0xA52A2A,
		'red3' => 0xCD0000,
		'IndianRed4' => 0x8B3A3A,
		'brown4' => 0x8B2323,
		'firebrick4' => 0x8B1A1A,
		'DarkRed' => 0x8B0000,
		'red4' => 0x8B0000,
		'maroon' => 0x800000,
		'LightPink1' => 0xFFAEB9,
		'LightPink3' => 0xCD8C95,
		'LightPink4' => 0x8B5F65,
		'LightPink2' => 0xEEA2AD,
		'LightPink' => 0xFFB6C1,
		'pink' => 0xFFC0CB,
		'crimson' => 0xDC143C,
		'pink1' => 0xFFB5C5,
		'pink2' => 0xEEA9B8,
		'pink3' => 0xCD919E,
		'pink4' => 0x8B636C,
		'PaleVioletRed4' => 0x8B475D,
		'PaleVioletRed' => 0xDB7093,
		'PaleVioletRed2' => 0xEE799F,
		'PaleVioletRed1' => 0xFF82AB,
		'PaleVioletRed3' => 0xCD6889,
		'LavenderBlush' => 0xFFF0F5,
		'LavenderBlush1' => 0xFFF0F5,
		'LavenderBlush3' => 0xCDC1C5,
		'LavenderBlush2' => 0xEEE0E5,
		'LavenderBlush4' => 0x8B8386,
		'maroon' => 0xB03060,
		'HotPink3' => 0xCD6090,
		'VioletRed3' => 0xCD3278,
		'VioletRed1' => 0xFF3E96,
		'VioletRed2' => 0xEE3A8C,
		'VioletRed4' => 0x8B2252,
		'HotPink2' => 0xEE6AA7,
		'HotPink1' => 0xFF6EB4,
		'HotPink4' => 0x8B3A62,
		'HotPink' => 0xFF69B4,
		'DeepPink' => 0xFF1493,
		'DeepPink1' => 0xFF1493,
		'DeepPink2' => 0xEE1289,
		'DeepPink3' => 0xCD1076,
		'DeepPink4' => 0x8B0A50,
		'maroon1' => 0xFF34B3,
		'maroon2' => 0xEE30A7,
		'maroon3' => 0xCD2990,
		'maroon4' => 0x8B1C62,
		'MediumVioletRed' => 0xC71585,
		'VioletRed' => 0xD02090,
		'orchid2' => 0xEE7AE9,
		'orchid' => 0xDA70D6,
		'orchid1' => 0xFF83FA,
		'orchid3' => 0xCD69C9,
		'orchid4' => 0x8B4789,
		'thistle1' => 0xFFE1FF,
		'thistle2' => 0xEED2EE,
		'plum1' => 0xFFBBFF,
		'plum2' => 0xEEAEEE,
		'thistle' => 0xD8BFD8,
		'thistle3' => 0xCDB5CD,
		'plum' => 0xDDA0DD,
		'violet' => 0xEE82EE,
		'plum3' => 0xCD96CD,
		'thistle4' => 0x8B7B8B,
		'fuchsia' => 0xFF00FF,
		'magenta' => 0xFF00FF,
		'magenta1' => 0xFF00FF,
		'plum4' => 0x8B668B,
		'magenta2' => 0xEE00EE,
		'magenta3' => 0xCD00CD,
		'DarkMagenta' => 0x8B008B,
		'magenta4' => 0x8B008B,
		'purple' => 0x800080,
		'MediumOrchid' => 0xBA55D3,
		'MediumOrchid1' => 0xE066FF,
		'MediumOrchid2' => 0xD15FEE,
		'MediumOrchid3' => 0xB452CD,
		'MediumOrchid4' => 0x7A378B,
		'DarkViolet' => 0x9400D3,
		'DarkOrchid' => 0x9932CC,
		'DarkOrchid1' => 0xBF3EFF,
		'DarkOrchid3' => 0x9A32CD,
		'DarkOrchid2' => 0xB23AEE,
		'DarkOrchid4' => 0x68228B,
		'purple' => 0xA020F0,
		'indigo' => 0x4B0082,
		'BlueViolet' => 0x8A2BE2,
		'purple2' => 0x912CEE,
		'purple3' => 0x7D26CD,
		'purple4' => 0x551A8B,
		'purple1' => 0x9B30FF,
		'MediumPurple' => 0x9370DB,
		'MediumPurple1' => 0xAB82FF,
		'MediumPurple2' => 0x9F79EE,
		'MediumPurple3' => 0x8968CD,
		'MediumPurple4' => 0x5D478B,
		'DarkSlateBlue' => 0x483D8B,
		'LightSlateBlue' => 0x8470FF,
		'MediumSlateBlue' => 0x7B68EE,
		'SlateBlue' => 0x6A5ACD,
		'SlateBlue1' => 0x836FFF,
		'SlateBlue2' => 0x7A67EE,
		'SlateBlue3' => 0x6959CD,
		'SlateBlue4' => 0x473C8B,
		'GhostWhite' => 0xF8F8FF,
		'lavender' => 0xE6E6FA,
		'blue' => 0x0000FF,
		'blue1' => 0x0000FF,
		'blue2' => 0x0000EE,
		'blue3' => 0x0000CD,
		'MediumBlue' => 0x0000CD,
		'blue4' => 0x00008B,
		'DarkBlue' => 0x00008B,
		'MidnightBlue' => 0x191970,
		'navy' => 0x000080,
		'NavyBlue' => 0x000080,
		'RoyalBlue' => 0x4169E1,
		'RoyalBlue1' => 0x4876FF,
		'RoyalBlue2' => 0x436EEE,
		'RoyalBlue3' => 0x3A5FCD,
		'RoyalBlue4' => 0x27408B,
		'CornflowerBlue' => 0x6495ED,
		'LightSteelBlue' => 0xB0C4DE,
		'LightSteelBlue1' => 0xCAE1FF,
		'LightSteelBlue2' => 0xBCD2EE,
		'LightSteelBlue3' => 0xA2B5CD,
		'LightSteelBlue4' => 0x6E7B8B,
		'SlateGray4' => 0x6C7B8B,
		'SlateGray1' => 0xC6E2FF,
		'SlateGray2' => 0xB9D3EE,
		'SlateGray3' => 0x9FB6CD,
		'LightSlateGray' => 0x778899,
		'LightSlateGrey' => 0x778899,
		'SlateGray' => 0x708090,
		'SlateGrey' => 0x708090,
		'DodgerBlue' => 0x1E90FF,
		'DodgerBlue1' => 0x1E90FF,
		'DodgerBlue2' => 0x1C86EE,
		'DodgerBlue4' => 0x104E8B,
		'DodgerBlue3' => 0x1874CD,
		'AliceBlue' => 0xF0F8FF,
		'SteelBlue4' => 0x36648B,
		'SteelBlue' => 0x4682B4,
		'SteelBlue1' => 0x63B8FF,
		'SteelBlue2' => 0x5CACEE,
		'SteelBlue3' => 0x4F94CD,
		'SkyBlue4' => 0x4A708B,
		'SkyBlue1' => 0x87CEFF,
		'SkyBlue2' => 0x7EC0EE,
		'SkyBlue3' => 0x6CA6CD,
		'LightSkyBlue' => 0x87CEFA,
		'LightSkyBlue4' => 0x607B8B,
		'LightSkyBlue1' => 0xB0E2FF,
		'LightSkyBlue2' => 0xA4D3EE,
		'LightSkyBlue3' => 0x8DB6CD,
		'SkyBlue' => 0x87CEEB,
		'LightBlue3' => 0x9AC0CD,
		'DeepSkyBlue' => 0x00BFFF,
		'DeepSkyBlue1' => 0x00BFFF,
		'DeepSkyBlue2' => 0x00B2EE,
		'DeepSkyBlue4' => 0x00688B,
		'DeepSkyBlue3' => 0x009ACD,
		'LightBlue1' => 0xBFEFFF,
		'LightBlue2' => 0xB2DFEE,
		'LightBlue' => 0xADD8E6,
		'LightBlue4' => 0x68838B,
		'PowderBlue' => 0xB0E0E6,
		'CadetBlue1' => 0x98F5FF,
		'CadetBlue2' => 0x8EE5EE,
		'CadetBlue3' => 0x7AC5CD,
		'CadetBlue4' => 0x53868B,
		'turquoise1' => 0x00F5FF,
		'turquoise2' => 0x00E5EE,
		'turquoise3' => 0x00C5CD,
		'turquoise4' => 0x00868B,
		'cadet blue' => 0x5F9EA0,
		'CadetBlue' => 0x5F9EA0,
		'DarkTurquoise' => 0x00CED1,
		'azure' => 0xF0FFFF,
		'azure1' => 0xF0FFFF,
		'LightCyan' => 0xE0FFFF,
		'LightCyan1' => 0xE0FFFF,
		'azure2' => 0xE0EEEE,
		'LightCyan2' => 0xD1EEEE,
		'PaleTurquoise1' => 0xBBFFFF,
		'PaleTurquoise' => 0xAFEEEE,
		'PaleTurquoise2' => 0xAEEEEE,
		'DarkSlateGray1' => 0x97FFFF,
		'azure3' => 0xC1CDCD,
		'LightCyan3' => 0xB4CDCD,
		'DarkSlateGray2' => 0x8DEEEE,
		'PaleTurquoise3' => 0x96CDCD,
		'DarkSlateGray3' => 0x79CDCD,
		'azure4' => 0x838B8B,
		'LightCyan4' => 0x7A8B8B,
		'aqua' => 0x00FFFF,
		'cyan' => 0x00FFFF,
		'cyan1' => 0x00FFFF,
		'PaleTurquoise4' => 0x668B8B,
		'cyan2' => 0x00EEEE,
		'DarkSlateGray4' => 0x528B8B,
		'cyan3' => 0x00CDCD,
		'cyan4' => 0x008B8B,
		'DarkCyan' => 0x008B8B,
		'teal' => 0x008080,
		'DarkSlateGray' => 0x2F4F4F,
		'DarkSlateGrey' => 0x2F4F4F,
		'MediumTurquoise' => 0x48D1CC,
		'LightSeaGreen' => 0x20B2AA,
		'turquoise' => 0x40E0D0,
		'aquamarine4' => 0x458B74,
		'aquamarine' => 0x7FFFD4,
		'aquamarine1' => 0x7FFFD4,
		'aquamarine2' => 0x76EEC6,
		'aquamarine3' => 0x66CDAA,
		'MediumAquamarine' => 0x66CDAA,
		'MediumSpringGreen' => 0x00FA9A,
		'MintCream' => 0xF5FFFA,
		'SpringGreen' => 0x00FF7F,
		'SpringGreen1' => 0x00FF7F,
		'SpringGreen2' => 0x00EE76,
		'SpringGreen3' => 0x00CD66,
		'SpringGreen4' => 0x008B45,
		'MediumSeaGreen' => 0x3CB371,
		'SeaGreen' => 0x2E8B57,
		'SeaGreen3' => 0x43CD80,
		'SeaGreen1' => 0x54FF9F,
		'SeaGreen4' => 0x2E8B57,
		'SeaGreen2' => 0x4EEE94,
		'MediumForestGreen' => 0x32814B,
		'honeydew' => 0xF0FFF0,
		'honeydew1' => 0xF0FFF0,
		'honeydew2' => 0xE0EEE0,
		'DarkSeaGreen1' => 0xC1FFC1,
		'DarkSeaGreen2' => 0xB4EEB4,
		'PaleGreen1' => 0x9AFF9A,
		'PaleGreen' => 0x98FB98,
		'honeydew3' => 0xC1CDC1,
		'LightGreen' => 0x90EE90,
		'PaleGreen2' => 0x90EE90,
		'DarkSeaGreen3' => 0x9BCD9B,
		'DarkSeaGreen' => 0x8FBC8F,
		'PaleGreen3' => 0x7CCD7C,
		'honeydew4' => 0x838B83,
		'green1' => 0x00FF00,
		'lime' => 0x00FF00,
		'LimeGreen' => 0x32CD32,
		'DarkSeaGreen4' => 0x698B69,
		'green2' => 0x00EE00,
		'PaleGreen4' => 0x548B54,
		'green3' => 0x00CD00,
		'ForestGreen' => 0x228B22,
		'green4' => 0x008B00,
		'green' => 0x008000,
		'DarkGreen' => 0x006400,
		'LawnGreen' => 0x7CFC00,
		'chartreuse' => 0x7FFF00,
		'chartreuse1' => 0x7FFF00,
		'chartreuse2' => 0x76EE00,
		'chartreuse3' => 0x66CD00,
		'chartreuse4' => 0x458B00,
		'GreenYellow' => 0xADFF2F,
		'DarkOliveGreen3' => 0xA2CD5A,
		'DarkOliveGreen1' => 0xCAFF70,
		'DarkOliveGreen2' => 0xBCEE68,
		'DarkOliveGreen4' => 0x6E8B3D,
		'DarkOliveGreen' => 0x556B2F,
		'OliveDrab' => 0x6B8E23,
		'OliveDrab1' => 0xC0FF3E,
		'OliveDrab2' => 0xB3EE3A,
		'OliveDrab3' => 0x9ACD32,
		'YellowGreen' => 0x9ACD32,
		'OliveDrab4' => 0x698B22,
		'ivory' => 0xFFFFF0,
		'ivory1' => 0xFFFFF0,
		'LightYellow' => 0xFFFFE0,
		'LightYellow1' => 0xFFFFE0,
		'beige' => 0xF5F5DC,
		'ivory2' => 0xEEEEE0,
		'LightGoldenrodYellow' => 0xFAFAD2,
		'LightYellow2' => 0xEEEED1,
		'ivory3' => 0xCDCDC1,
		'LightYellow3' => 0xCDCDB4,
		'ivory4' => 0x8B8B83,
		'LightYellow4' => 0x8B8B7A,
		'yellow' => 0xFFFF00,
		'yellow1' => 0xFFFF00,
		'yellow2' => 0xEEEE00,
		'yellow3' => 0xCDCD00,
		'yellow4' => 0x8B8B00,
		'olive' => 0x808000,
		'DarkKhaki' => 0xBDB76B,
		'khaki2' => 0xEEE685,
		'LemonChiffon4' => 0x8B8970,
		'khaki1' => 0xFFF68F,
		'khaki3' => 0xCDC673,
		'khaki4' => 0x8B864E,
		'PaleGoldenrod' => 0xEEE8AA,
		'LemonChiffon' => 0xFFFACD,
		'LemonChiffon1' => 0xFFFACD,
		'khaki' => 0xF0E68C,
		'LemonChiffon3' => 0xCDC9A5,
		'LemonChiffon2' => 0xEEE9BF,
		'MediumGoldenRod' => 0xD1C166,
		'cornsilk4' => 0x8B8878,
		'gold' => 0xFFD700,
		'gold1' => 0xFFD700,
		'gold2' => 0xEEC900,
		'gold3' => 0xCDAD00,
		'gold4' => 0x8B7500,
		'LightGoldenrod' => 0xEEDD82,
		'LightGoldenrod4' => 0x8B814C,
		'LightGoldenrod1' => 0xFFEC8B,
		'LightGoldenrod3' => 0xCDBE70,
		'LightGoldenrod2' => 0xEEDC82,
		'cornsilk3' => 0xCDC8B1,
		'cornsilk2' => 0xEEE8CD,
		'cornsilk' => 0xFFF8DC,
		'cornsilk1' => 0xFFF8DC,
		'goldenrod' => 0xDAA520,
		'goldenrod1' => 0xFFC125,
		'goldenrod2' => 0xEEB422,
		'goldenrod3' => 0xCD9B1D,
		'goldenrod4' => 0x8B6914,
		'DarkGoldenrod' => 0xB8860B,
		'DarkGoldenrod1' => 0xFFB90F,
		'DarkGoldenrod2' => 0xEEAD0E,
		'DarkGoldenrod3' => 0xCD950C,
		'DarkGoldenrod4' => 0x8B6508,
		'FloralWhite' => 0xFFFAF0,
		'wheat2' => 0xEED8AE,
		'OldLace' => 0xFDF5E6,
		'wheat' => 0xF5DEB3,
		'wheat1' => 0xFFE7BA,
		'wheat3' => 0xCDBA96,
		'orange' => 0xFFA500,
		'orange1' => 0xFFA500,
		'orange2' => 0xEE9A00,
		'orange3' => 0xCD8500,
		'orange4' => 0x8B5A00,
		'wheat4' => 0x8B7E66,
		'moccasin' => 0xFFE4B5,
		'PapayaWhip' => 0xFFEFD5,
		'NavajoWhite3' => 0xCDB38B,
		'BlanchedAlmond' => 0xFFEBCD,
		'NavajoWhite' => 0xFFDEAD,
		'NavajoWhite1' => 0xFFDEAD,
		'NavajoWhite2' => 0xEECFA1,
		'NavajoWhite4' => 0x8B795E,
		'AntiqueWhite4' => 0x8B8378,
		'AntiqueWhite' => 0xFAEBD7,
		'tan' => 0xD2B48C,
		'bisque4' => 0x8B7D6B,
		'burlywood' => 0xDEB887,
		'AntiqueWhite2' => 0xEEDFCC,
		'burlywood1' => 0xFFD39B,
		'burlywood3' => 0xCDAA7D,
		'burlywood2' => 0xEEC591,
		'AntiqueWhite1' => 0xFFEFDB,
		'burlywood4' => 0x8B7355,
		'AntiqueWhite3' => 0xCDC0B0,
		'DarkOrange' => 0xFF8C00,
		'bisque2' => 0xEED5B7,
		'bisque' => 0xFFE4C4,
		'bisque1' => 0xFFE4C4,
		'bisque3' => 0xCDB79E,
		'DarkOrange1' => 0xFF7F00,
		'linen' => 0xFAF0E6,
		'DarkOrange2' => 0xEE7600,
		'DarkOrange3' => 0xCD6600,
		'DarkOrange4' => 0x8B4500,
		'peru' => 0xCD853F,
		'tan1' => 0xFFA54F,
		'tan2' => 0xEE9A49,
		'tan3' => 0xCD853F,
		'tan4' => 0x8B5A2B,
		'PeachPuff' => 0xFFDAB9,
		'PeachPuff1' => 0xFFDAB9,
		'PeachPuff4' => 0x8B7765,
		'PeachPuff2' => 0xEECBAD,
		'PeachPuff3' => 0xCDAF95,
		'SandyBrown' => 0xF4A460,
		'seashell4' => 0x8B8682,
		'seashell2' => 0xEEE5DE,
		'seashell3' => 0xCDC5BF,
		'chocolate' => 0xD2691E,
		'chocolate1' => 0xFF7F24,
		'chocolate2' => 0xEE7621,
		'chocolate3' => 0xCD661D,
		'chocolate4' => 0x8B4513,
		'SaddleBrown' => 0x8B4513,
		'seashell' => 0xFFF5EE,
		'seashell1' => 0xFFF5EE,
		'sienna4' => 0x8B4726,
		'sienna' => 0xA0522D,
		'sienna1' => 0xFF8247,
		'sienna2' => 0xEE7942,
		'sienna3' => 0xCD6839,
		'LightSalmon3' => 0xCD8162,
		'LightSalmon' => 0xFFA07A,
		'LightSalmon1' => 0xFFA07A,
		'LightSalmon4' => 0x8B5742,
		'LightSalmon2' => 0xEE9572,
		'coral' => 0xFF7F50,
		'OrangeRed' => 0xFF4500,
		'OrangeRed1' => 0xFF4500,
		'OrangeRed2' => 0xEE4000,
		'OrangeRed3' => 0xCD3700,
		'OrangeRed4' => 0x8B2500,
		'DarkSalmon' => 0xE9967A,
		'salmon1' => 0xFF8C69,
		'salmon2' => 0xEE8262,
		'salmon3' => 0xCD7054,
		'salmon4' => 0x8B4C39,
		'coral1' => 0xFF7256,
		'coral2' => 0xEE6A50,
		'coral3' => 0xCD5B45,
		'coral4' => 0x8B3E2F,
		'tomato4' => 0x8B3626,
		'tomato' => 0xFF6347,
		'tomato1' => 0xFF6347,
		'tomato2' => 0xEE5C42,
		'tomato3' => 0xCD4F39,
		'MistyRose4' => 0x8B7D7B,
		'MistyRose2' => 0xEED5D2,
		'MistyRose' => 0xFFE4E1,
		'MistyRose1' => 0xFFE4E1,
		'salmon' => 0xFA8072,
		'MistyRose3' => 0xCDB7B5,
		'white' => 0xFFFFFF,
		'gray100' => 0xFFFFFF,
		'grey100' => 0xFFFFFF,
		'grey100' => 0xFFFFFF,
		'gray99' => 0xFCFCFC,
		'grey99' => 0xFCFCFC,
		'gray98' => 0xFAFAFA,
		'grey98' => 0xFAFAFA,
		'gray97' => 0xF7F7F7,
		'grey97' => 0xF7F7F7,
		'gray96' => 0xF5F5F5,
		'grey96' => 0xF5F5F5,
		'WhiteSmoke' => 0xF5F5F5,
		'gray95' => 0xF2F2F2,
		'grey95' => 0xF2F2F2,
		'gray94' => 0xF0F0F0,
		'grey94' => 0xF0F0F0,
		'gray93' => 0xEDEDED,
		'grey93' => 0xEDEDED,
		'gray92' => 0xEBEBEB,
		'grey92' => 0xEBEBEB,
		'gray91' => 0xE8E8E8,
		'grey91' => 0xE8E8E8,
		'gray90' => 0xE5E5E5,
		'grey90' => 0xE5E5E5,
		'gray89' => 0xE3E3E3,
		'grey89' => 0xE3E3E3,
		'gray88' => 0xE0E0E0,
		'grey88' => 0xE0E0E0,
		'gray87' => 0xDEDEDE,
		'grey87' => 0xDEDEDE,
		'gainsboro' => 0xDCDCDC,
		'gray86' => 0xDBDBDB,
		'grey86' => 0xDBDBDB,
		'gray85' => 0xD9D9D9,
		'grey85' => 0xD9D9D9,
		'gray84' => 0xD6D6D6,
		'grey84' => 0xD6D6D6,
		'gray83' => 0xD4D4D4,
		'grey83' => 0xD4D4D4,
		'LightGray' => 0xD3D3D3,
		'LightGrey' => 0xD3D3D3,
		'gray82' => 0xD1D1D1,
		'grey82' => 0xD1D1D1,
		'gray81' => 0xCFCFCF,
		'grey81' => 0xCFCFCF,
		'gray80' => 0xCCCCCC,
		'grey80' => 0xCCCCCC,
		'gray79' => 0xC9C9C9,
		'grey79' => 0xC9C9C9,
		'gray78' => 0xC7C7C7,
		'grey78' => 0xC7C7C7,
		'gray77' => 0xC4C4C4,
		'grey77' => 0xC4C4C4,
		'gray76' => 0xC2C2C2,
		'grey76' => 0xC2C2C2,
		'silver' => 0xC0C0C0,
		'gray75' => 0xBFBFBF,
		'grey75' => 0xBFBFBF,
		'gray' => 0xBEBEBE,
		'grey' => 0xBEBEBE,
		'gray74' => 0xBDBDBD,
		'grey74' => 0xBDBDBD,
		'gray73' => 0xBABABA,
		'grey73' => 0xBABABA,
		'gray72' => 0xB8B8B8,
		'grey72' => 0xB8B8B8,
		'gray71' => 0xB5B5B5,
		'grey71' => 0xB5B5B5,
		'gray70' => 0xB3B3B3,
		'grey70' => 0xB3B3B3,
		'gray69' => 0xB0B0B0,
		'grey69' => 0xB0B0B0,
		'gray68' => 0xADADAD,
		'grey68' => 0xADADAD,
		'gray67' => 0xABABAB,
		'grey67' => 0xABABAB,
		'DarkGray' => 0xA9A9A9,
		'DarkGrey' => 0xA9A9A9,
		'gray66' => 0xA8A8A8,
		'grey66' => 0xA8A8A8,
		'gray65' => 0xA6A6A6,
		'grey65' => 0xA6A6A6,
		'gray64' => 0xA3A3A3,
		'grey64' => 0xA3A3A3,
		'gray63' => 0xA1A1A1,
		'grey63' => 0xA1A1A1,
		'gray62' => 0x9E9E9E,
		'grey62' => 0x9E9E9E,
		'gray61' => 0x9C9C9C,
		'grey61' => 0x9C9C9C,
		'gray60' => 0x999999,
		'grey60' => 0x999999,
		'gray59' => 0x969696,
		'grey59' => 0x969696,
		'gray58' => 0x949494,
		'grey58' => 0x949494,
		'gray57' => 0x919191,
		'grey57' => 0x919191,
		'gray56' => 0x8F8F8F,
		'grey56' => 0x8F8F8F,
		'gray55' => 0x8C8C8C,
		'grey55' => 0x8C8C8C,
		'gray54' => 0x8A8A8A,
		'grey54' => 0x8A8A8A,
		'gray53' => 0x878787,
		'grey53' => 0x878787,
		'gray52' => 0x858585,
		'grey52' => 0x858585,
		'gray51' => 0x828282,
		'grey51' => 0x828282,
		'fractal' => 0x808080,
		'gray50' => 0x7F7F7F,
		'grey50' => 0x7F7F7F,
		'gray' => 0x7E7E7E,
		'gray49' => 0x7D7D7D,
		'grey49' => 0x7D7D7D,
		'gray48' => 0x7A7A7A,
		'grey48' => 0x7A7A7A,
		'gray47' => 0x787878,
		'grey47' => 0x787878,
		'gray46' => 0x757575,
		'grey46' => 0x757575,
		'gray45' => 0x737373,
		'grey45' => 0x737373,
		'gray44' => 0x707070,
		'grey44' => 0x707070,
		'gray43' => 0x6E6E6E,
		'grey43' => 0x6E6E6E,
		'gray42' => 0x6B6B6B,
		'grey42' => 0x6B6B6B,
		'DimGray' => 0x696969,
		'DimGrey' => 0x696969,
		'gray41' => 0x696969,
		'grey41' => 0x696969,
		'gray40' => 0x666666,
		'grey40' => 0x666666,
		'gray39' => 0x636363,
		'grey39' => 0x636363,
		'gray38' => 0x616161,
		'grey38' => 0x616161,
		'gray37' => 0x5E5E5E,
		'grey37' => 0x5E5E5E,
		'gray36' => 0x5C5C5C,
		'grey36' => 0x5C5C5C,
		'gray35' => 0x595959,
		'grey35' => 0x595959,
		'gray34' => 0x575757,
		'grey34' => 0x575757,
		'gray33' => 0x545454,
		'grey33' => 0x545454,
		'gray32' => 0x525252,
		'grey32' => 0x525252,
		'gray31' => 0x4F4F4F,
		'grey31' => 0x4F4F4F,
		'gray30' => 0x4D4D4D,
		'grey30' => 0x4D4D4D,
		'gray29' => 0x4A4A4A,
		'grey29' => 0x4A4A4A,
		'gray28' => 0x474747,
		'grey28' => 0x474747,
		'gray27' => 0x454545,
		'grey27' => 0x454545,
		'gray26' => 0x424242,
		'grey26' => 0x424242,
		'gray25' => 0x404040,
		'grey25' => 0x404040,
		'gray24' => 0x3D3D3D,
		'grey24' => 0x3D3D3D,
		'gray23' => 0x3B3B3B,
		'grey23' => 0x3B3B3B,
		'gray22' => 0x383838,
		'grey22' => 0x383838,
		'gray21' => 0x363636,
		'grey21' => 0x363636,
		'gray20' => 0x333333,
		'grey20' => 0x333333,
		'gray19' => 0x303030,
		'grey19' => 0x303030,
		'gray18' => 0x2E2E2E,
		'grey18' => 0x2E2E2E,
		'gray17' => 0x2B2B2B,
		'grey17' => 0x2B2B2B,
		'gray16' => 0x292929,
		'grey16' => 0x292929,
		'gray15' => 0x262626,
		'grey15' => 0x262626,
		'gray14' => 0x242424,
		'grey14' => 0x242424,
		'gray13' => 0x212121,
		'grey13' => 0x212121,
		'gray12' => 0x1F1F1F,
		'grey12' => 0x1F1F1F,
		'gray11' => 0x1C1C1C,
		'grey11' => 0x1C1C1C,
		'gray10' => 0x1A1A1A,
		'grey10' => 0x1A1A1A,
		'gray9' => 0x171717,
		'grey9' => 0x171717,
		'gray8' => 0x141414,
		'grey8' => 0x141414,
		'gray7' => 0x121212,
		'grey7' => 0x121212,
		'gray6' => 0x0F0F0F,
		'grey6' => 0x0F0F0F,
		'gray5' => 0x0D0D0D,
		'grey5' => 0x0D0D0D,
		'gray4' => 0x0A0A0A,
		'grey4' => 0x0A0A0A,
		'gray3' => 0x080808,
		'grey3' => 0x080808,
		'gray2' => 0x050505,
		'grey2' => 0x050505,
		'gray1' => 0x030303,
		'grey1' => 0x030303,
		'black' => 0x000000,
		'gray0' => 0x000000,
		'grey0' => 0x000000
	);
}
