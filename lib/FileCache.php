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

class FileCache
{
	const T_COMPRESS = 'compress';
	const T_REPOSITORY = 'repository';
	const T_REPOSITORY_DELETE_RATIO = 'repository_delete_ratio';
	const T_REPOSITORY_SIZE = 'repository_size';
	const T_SERIALIZE = 'serialize';
	const T_MODIFIED_TIME = 'modified_time';

	public $compress = false;
	public $repository;
	public $repository_delete_ratio = .25;
	public $repository_size = 512;
	public $serialize = false;
	public $modified_time;

	protected $root;

	public function __construct(array $tags)
	{
		if (empty($tags[self::T_REPOSITORY]))
		{
			throw new \Exception(format('The %tag tag is required', [ '%tag' => 'T_REPOSITORY' ]));
		}

		foreach ($tags as $tag => $value)
		{
			$this->$tag = $value;
		}

		if (strpos($this->repository, DOCUMENT_ROOT) === 0)
		{
			$this->repository = substr($this->repository, strlen(DOCUMENT_ROOT) - 1);
		}

		$this->root = realpath(\ICanBoogie\DOCUMENT_ROOT . $this->repository);
	}

	/**
	 * Check if a file exists in the repository.
	 *
	 * If the file does not exists, it's created using the provided constructor.
	 *
	 * The working directory is changed to the repository during the process.
	 *
	 * @param string $file The name of the file in the repository.
	 *
	 * @param callable $constructor The constructor for the file.
	 * The constructor is called with the cache object, the name of the file and the userdata.
	 *
	 * @param mixed $userdata Userdata that will be passed to the constructor.
	 *
	 * @return mixed The URL of the file. FALSE is the file failed to be created.
	 */

	public function get($file, $constructor, $userdata=null)
	{
		if (!is_dir($this->root))
		{
			throw new \Exception(format('The repository %repository does not exists.', [ '%repository' => $this->repository ], 404));
		}

		$location = getcwd();

		chdir($this->root);

		if (!is_file($file) || ($this->modified_time && $this->modified_time > filemtime($file)))
		{
			$file = call_user_func($constructor, $this, $file, $userdata);
		}

		chdir($location);

		return $file ? $this->repository . '/' . $file : $file;
	}

	public function exists($key)
	{
		$location = getcwd();

		chdir($this->root);

		$rc = file_exists($key);

		chdir($location);

		return $rc;
	}

	/**
	 * Load cached contents.
	 *
	 * If the content is not cached, the constructor is called to create the content.
	 * The content generated by the constructor is save to the cache.
	 *
	 * @param string $key Key for the value in the cache.
	 * @param callable $constructor Constructor callback. The constructor is called to
	 * generated the contents of the file. The constructor is called with the FileCache
	 * object, the @file and the @userdata as arguments.
	 * @param mixed $userdata User data that is passed as is to the constructor.
	 *
	 * @return mixed The contents of the file
	 */
	public function load($key, $constructor, $userdata=null)
	{
		#
		# if the repository does not exists we simply return the contents
		# created by the constructor.
		#

		if (!is_dir($this->root))
		{
			throw new \Exception(format('The repository %repository does not exists.', [ '%repository' => $this->repository ], 404));

			return call_user_func($contructor, $userdata, $this, $key);
		}

		#
		#
		#

		$location = getcwd();

		chdir($this->root);

		$contents = null;

		if (is_readable($key))
		{
			$contents = file_get_contents($key);

			if ($this->compress)
			{
				$contents = gzinflate($contents);
			}

			if ($this->serialize)
			{
				$contents = unserialize($contents);
			}
		}

		if ($contents === null)
		{
			$contents = call_user_func($constructor, $userdata, $this, $key);

			$this->save($key, $contents);
		}

		chdir($location);

		return $contents;
	}

	/**
	 * Save contents to a cached file.
	 *
	 * @param $file string Name of the file.
	 * @param $contents mixed The contents to write.
	 *
	 * @return int Return value from @file_put_contents()
	 */
	protected function save($file, $contents)
	{
		if (!is_writable($this->root))
		{
			throw new \Exception(format('The repository %repository is not writable.', [ '%repository' => $this->repository ]));
		}

		$location = getcwd();

		chdir($this->root);

		if ($this->serialize)
		{
			$contents = serialize($contents);
		}

		if ($this->compress)
		{
			$contents = gzdeflate($contents);
		}

		$rc = file_put_contents($file, $contents, LOCK_EX);

		chdir($location);

		return $rc;
	}

	public function store($key, $data)
	{
		return $this->save($key, $data);
	}

	public function retrieve($key)
	{
		$location = getcwd();

		chdir($this->root);

		$rc = file_get_contents($key);

		chdir($location);

		return $rc;
	}

	public function delete($file)
	{
		return $this->unlink([ $file => true ]);
	}

	/**
	 * Read to repository and return an array of files.
	 *
	 * Each entry in the array is made up using the _ctime_ and _size_ of the file. The
	 * key of the entry is the file name.
	 *
	 * @return unknown_type
	 */

	protected function read()
	{
		$root = $this->root;

		if (!is_dir($root))
		{
			return false;
		}

		try
		{
			$dir = new \DirectoryIterator($root);
		}
		catch (\UnexpectedValueException $e)
		{
			throw new \Exception(format('Unable to open directory %root', [ '%root' => $root ]));
		}

		#
		# create file list, with the filename as key and ctime and size as value.
		# we set the ctime first to be able to sort the file by ctime when necessary.
		#

		$files = [];

		foreach ($dir as $file)
		{
			if (!$file->isDot())
			{
				$files[$file->getFilename()] = [ $file->getCTime(), $file->getSize() ];
			}
		}

		return $files;
	}

	protected function unlink($files)
	{
		if (!$files)
		{
			return;
		}

		#
		# change the working directory to the repository
		#

		$location = getcwd();

		chdir($this->root);

		#
		# obtain exclusive lock to delete files
		#

		$lh = fopen('.lock', 'w+');

		if (!$lh)
		{
			Debug::trigger('Unable to lock %repository', [ '%repository' => $this->repository ]);

			chdir($location);

			return;
		}

		#
		# We will try $n time to obtain the exclusive lock
		#

		$n = 10;

		while (!flock($lh, LOCK_EX | LOCK_NB))
		{
			#
			# If the lock is not obtained we sleep for 0 to 100 milliseconds.
			# We sleep to avoid CPU load, and we sleep for a random time
			# to avoid collision.
			#

			usleep(round(rand(0, 100) * 1000));

			if (!--$n)
			{
				#
				# We were unable to obtain the lock in time.
				# We exit silently.
				#

				chdir($location);

				return;
			}
		}

		#
		# The lock was obtained, we can now delete the files
		#

		foreach ($files as $file => $dummy)
		{
			#
			# Because of concurrent access, the file might have already been deleted.
			# We have to check if the file still exists before calling unlink()
			#

			if (!file_exists($file))
			{
				continue;
			}

			unlink($file);
		}

		chdir($location);

		#
		# and release the lock.
		#

		fclose($lh);
	}

	/**
	 *
	 * Clear all the files in the repository.
	 *
	 */

	public function clear()
	{
		$files = $this->read();

		return $this->unlink($files);
	}

	/**
	 *
	 * Clean the repository according to the size and time rules.
	 *
	 */

	public function clean()
	{
		$files = $this->read();

		if (!$files)
		{
			return;
		}

		$totalsize = 0;

		foreach ($files as $stat)
		{
			$totalsize += $stat[1];
		}

		$repository_size = $this->repository_size * 1024;

		if ($totalsize < $repository_size)
		{
			#
			# There is enough space in the repository. We don't need to delete any file.
			#

			return;
		}

		#
		# The repository is completely full, we need to make some space.
		# We create an array with the files to delete. Files are added until
		# the delete ratio is reached.
		#

		asort($files);

		$deletesize = $repository_size * $this->repository_delete_ratio;

		$i = 0;

		foreach ($files as $file => $stat)
		{
			$i++;

			$deletesize -= $stat[1];

			if ($deletesize < 0)
			{
				break;
			}
		}

		$files = array_slice($files, 0, $i);

		return $this->unlink($files);
	}
}
