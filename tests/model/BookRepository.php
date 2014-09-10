<?php

namespace Model\Repositories;

use YetORM;
use Model\Entities\Book;
use Nette\Database\Context as NdbContext;
use Nette\Database\Table\ActiveRow as NActiveRow;


/**
 * @table  book
 * @entity Book
 * @method Book|NULL getByBook_title(string $title)
 * @method \YetORM\EntityCollection|Book[] findByAvailable(bool $available)
 */
class BookRepository extends YetORM\Repository
{

	/** @var string */
	private $imageDir;


	/**
	 * @param  NdbContext $context
	 * @param  string $imageDir
	 */
	function __construct(NdbContext $context, $imageDir)
	{
		parent::__construct($context);

		$realpath = realpath($imageDir);

		if ($realpath === FALSE || !is_dir($realpath)) {
			throw new \InvalidArgumentException;
		}

		$this->imageDir = $realpath;
	}


	/**
	 * @param  Book $book
	 * @return bool
	 */
	function persist(YetORM\Entity $book)
	{
		$me = $this;

		return $this->transaction(function () use ($me, $book) {

			$book->onPersist($book);
			$status = parent::persist($book);

			// persist tags
			if (count($book->getAddedTags()) || count($book->getRemovedTags())) {
				$tags = $this->getTable('tag')->fetchPairs('name', 'id');
				foreach ($book->getAddedTags() as $tag) {
					if (!isset($tags[$tag->name])) {
						$tags[$tag->name] = $tagID = $this->getTable('tag')->insert(array(
							'name' => $tag->name,
						))->id;
					}

					$this->getTable('book_tag')->insert(array(
						'book_id' => $book->id,
						'tag_id' => $tags[$tag->name],
					));
				}

				$toDelete = array();
				foreach ($book->getRemovedTags() as $tag) {
					if (isset($tags[$tag->name])) {
						$toDelete[] = $tags[$tag->name];
					}
				}

				if (count($toDelete)) {
					$this->getTable('book_tag')
							->where('book_id', $book->id)
							->where('tag_id', $toDelete)
							->delete();
				}
			}

			return $status;

		});
	}


	/** @return YetORM\EntityCollection|Book[] */
	function findByTag($name)
	{
		return $this->createCollection($this->getTable()->where(':book_tag.tag.name', $name));
	}


	/**
	 * @param  NActiveRow|Record $row
	 * @return Book
	 */
	function createEntity($row = NULL)
	{
		return new Book($row, $this->imageDir);
	}


	/** @return string */
	function getImageDir()
	{
		return $this->imageDir;
	}


	/**
	 * @param  \Exception $e
	 * @return void
	 */
	protected function handleException(\Exception $e)
	{
		if ((int) $e->getCode() === 23000) {
			throw new DuplicateEntryException;
		}
	}

}


class DuplicateEntryException extends \Exception
{}
