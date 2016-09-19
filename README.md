# php-installer

[![packagist](https://poser.pugx.org/prismic/installer/version)](https://packagist.org/packages/prismic/installer)

CLI to bootstrap Prismic.io projects in PHP.

Install it globally:

```
composer global require "prismic/installer"
```

## Usage

Just calling `prismic` will display the manual.

To create a new project for the 'foobar' repository:

```
prismic init foobar
```

By default it will create the project in a directory using the name of the repository. You can specify a different folder, here 'baz':

```
prismic init --folder=baz foobar
```
