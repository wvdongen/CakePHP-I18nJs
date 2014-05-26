# I18nJs Plugin for CakePHP #

A CakePHP 2.x plugin to translate JavaScript strings. The JavaScript functions and methods to fetch the translation strings are taken from Drupal 8.

## How to include

* Put the files in `APP/Plugin/I18nJs`
* Make sure you have `CakePlugin::load('I18nJs')` or `CakePlugin::loadAll()` in your bootstrap
* Include the JavaScript lib to your HTML `$this->Html->script('/i18n_js/js/18n_js')`
* Add generated JavaScript translation file `$this->Html->script('/js/Locale/i18n_js.' . $this->Session->read('Config.language'))`

## JavaScript functions

### I18nJs.t(str, args, options)
Translate strings to the page language or a given language.

Examples:
```Javascript
I18nJs.t('This string needs transalation');
I18nJs.t('Welcome @name', {'@name': 'Wouter'});
```

### I18nJs.t(count, singular, plural, args, options)
Format a string containing a count of items.

Examples:
```Javascript
Drupal.formatPlural(count, '@name has 1 site.', '@name has @count sites.', {'@name': personName});
Drupal.formatPlural(count, '1 comment', '@count comments');
```

## Generate .pot file
```php
Console/cake I18nJs.i18n_js extract_js
```

This will parse all the javascript translation functions from your .js and .ctp files. This will create the file `App/Locale/i18n_js.pot`. 

## Create JavaScript translation file

Make sure your translations are located in `App/Locale/<language>/LC_MESSAGES/i18n_js.po`.

```php
Console/cake I18nJs.i18n_js generate_js
```

This will create JavaScript file(s) as `App/webroot/js/Locale/i18n_js.<language>.js`. 

This fill should be added to your HTML. Add for example the following to your `default.ctp` file:
```php
echo $this->Html->script('/js/Locale/i18n_js.' . $this->Session->read('Config.language'));
```