YetORM
======

[![Build Status](https://travis-ci.org/uestla/YetORM.svg?branch=master)](https://travis-ci.org/uestla/YetORM)
[![Code coverage](https://coveralls.io/repos/github/uestla/YetORM/badge.svg?branch=master)](https://coveralls.io/r/uestla/YetORM)
[![Total downloads](https://poser.pugx.org/uestla/yetorm/downloads)](https://packagist.org/packages/uestla/YetORM)
[![Latest stable](https://poser.pugx.org/uestla/yetorm/v/stable)](https://packagist.org/packages/uestla/YetORM)

Lightweight ORM built on top of Nette\Database

[![Buy me a Coffee](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T4AW48GVJW8KY)

Quickstart
----------

Consider following database schema:

![Database schema](http://i.imgur.com/EtR1bM4.png)

### Entities

Firstly we'll create entity classes according to the schema above. There are two ways of defining entity properties - via `@property[-read]` annotation, or simply via getter and setter.

#### Tag

```php
/**
 * @property-read int $id
 * @property string $name
 */
class Tag extends YetORM\Entity
{}
```

#### Author

```php
/**
 * @property-read int $id
 * @property string $name
 * @property string $web
 * @property \DateTime $born
 */
class Author extends YetORM\Entity
{}
```

#### Book

There are some relations at the `Book` entity - two N:1 `Author` and M:N `Tag` relations. Every `YetORM\Entity` has an instance of `YetORM\Record` in it, which is a simple wrapper around `Nette\Database\Table\ActiveRow`. That means that we can access related records or column values through it.

```php
/**
 * @property-read int $id
 * @property string $title
 * @property string $web
 * @property string $slogan
 */
class Book extends YetORM\Entity
{
	function getAuthor()
	{
		return new Author($this->record->ref('author', 'author_id'));
	}

	function getMaintainer()
	{
		return new Author($this->record->ref('author', 'maintainer_id'));
	}

	function getTags()
	{
		$selection = $this->record->related('book_tag');
		return new YetORM\EntityCollection($selection, 'Tag', 'tag');
	}
}
```

With `$record->ref($table, $column)` we're accessing related table row in table `$table` through column `$column` - pretty simple.

The M:N relation is realized with `YetORM\EntityCollection` instance - which is a lazy collection of entities. In this case it iterates throw all related rows from `book_tag` table (first argument), creates instances of `Tag` (second argument) and on every related `book_tag` table row it accesses related `tag` table row (third argument), which then passes to the constructor of `Tag` entity :-)

This sounds crazy, but it's actually simple to get used to.

With this knowledge we can now simply add some helpful methods to `Author` entity:

```php
// class Author
function getBooksWritten()
{
	$selection = $this->record->related('book', 'author_id');
	return new YetORM\EntityCollection($selection, 'Book');
}

function getBooksMaintained()
{
	$selection = $this->record->related('book', 'maintainer_id');
	return new YetORM\EntityCollection($selection, 'Book');
}
```


### Repositories

Every repository has to have table and entity class name defined - either via `@table` and `@entity` annotaion, or via protected `$table` and `$entity` class property.

```php
/**
 * @table  book
 * @entity Book
 */
class BookRepository extends YetORM\Repository
{}
```

#### Fetching collections

`YetORM\Repository` comes with basic `findAll()` and `findBy($criteria)` methods, both returning already mentioned `EntityCollection`.

We can simply iterate through all books

```php
$books = new BookRepository($connection); // $connection instanceof Nette\Database\Context

foreach ($books->findAll() as $book) { // $book instanceof Book
	echo $book->title;
	echo $book->getAuthor()->name;
	foreach ($book->getTags() as $tag) { // $tag instanceof Tag
		echo $tag->name;
	}
}
```

#### Fetching single entity

```php
$book = $books->getByID(123); // instanceof Book or NULL if not found
```

#### Magic `findBy<Property>()` and `getBy<Property>()` methods

Instead of manually writing `findByTitle($title)` method as this

```php
function findByTitle($title)
{
	return $this->findBy(array(
		'title' => $title,
	));
}
```

we can just call

```php
$books->findByTitle($title); // without having the method implemented
```

That will return a collection of books with that exact title.

To get a single entity use the magic `getBy<Property>($value)` method:

```php
$book = $books->getByIsbn('<isbn_code>'); // instanceof Book or NULL if not found
```

Just to have the IDE code completion along with this magic methods, we can use the `@method` annotation:

```php
/**
 * @table  book
 * @entity Book
 * @method YetORM\EntityCollection|Book[] findByTitle(string $title)
 * @method Book|NULL getByIsbn(string $isbn)
 */
class BookRepository extends YetORM\Repository
{}

/**
 * @property-read int $id
 * @property string $title
 * @property string $isbn
 */
 class Book extends Entity
 {}
```

> IMPORTANT: When using magic `findBy<Property>()` and `getBy<Property>()` methods, make sure you have the property defined via `@property` annotation!

> NOTE: magic `findBy<Property>()` and `getBy<Property>()` do not work on relational properties of type Entity.


#### Persisting

To persist changes we simply call `$repository->persist($entity)`.

```php
$book->web = 'http://example.com';
$books->persist($book);
```


And that's it!


Additional notes
----------------

- **No identity map**
- **Query efficiency** - the collections (resp. `YetORM\Record`) use the power of `Nette\Database` efficiency
- **Collection operations** - collections can be sorted via `$coll->orderBy($column, $dir)` and limitted via `$coll->limit($limit, $offset)`


More
----

For more examples please see the [tests](https://github.com/uestla/YetORM/tree/master/tests).
