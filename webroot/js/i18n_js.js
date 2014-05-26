/**
 * This JS code has been copied from Drupal 8 (drupal.js).
 * Drupal functions have been renamed to I18nJs.
 *
 * @author Wouter van Dongen
 */

window.I18nJs = { locale: {} };

/**
 * Encode special characters in a plain-text string for display as HTML.
 *
 * @param str
 *   The string to be encoded.
 * @return
 *   The encoded string.
 * @ingroup sanitization
 */
I18nJs.checkPlain = function (str) {
	str = str.toString()
		.replace(/&/g, '&amp;')
		.replace(/"/g, '&quot;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;');
	return str;
};

/**
 * Replace placeholders with sanitized values in a string.
 *
 * @param str
 *   A string with placeholders.
 * @param args
 *   An object of replacements pairs to make. Incidences of any key in this
 *   array are replaced with the corresponding value. Based on the first
 *   character of the key, the value is escaped and/or themed:
 *    - !variable: inserted as is
 *    - @variable: escape plain text to HTML (I18nJs.checkPlain)
 *    - %variable: escape text and theme as a placeholder for user-submitted
 *      content (checkPlain + I18nJs.theme('placeholder'))
 *
 * @see I18nJs.t()
 * @ingroup sanitization
 */
I18nJs.formatString = function(str, args) {
	// Transform arguments before inserting them.
	for (var key in args) {
		if (args.hasOwnProperty(key)) {
			switch (key.charAt(0)) {
				// Escaped only.
				case '@':
					args[key] = I18nJs.checkPlain(args[key]);
				break;
				// Pass-through.
				case '!':
					break;
				// Escaped and placeholder.
				default:
					args[key] = I18nJs.theme('placeholder', args[key]);
					break;
			}
			str = str.replace(key, args[key]);
		}
	}
	return str;
};

/**
 * Translate strings to the page language or a given language.
 *
 * See the documentation of the server-side t() function for further details.
 *
 * @param str
 *   A string containing the English string to translate.
 * @param args
 *   An object of replacements pairs to make after translation. Incidences
 *   of any key in this array are replaced with the corresponding value.
 *   See I18nJs.formatString().
 *
 * @param options
 *   - 'context' (defaults to the empty context): The context the source string
 *     belongs to.
 *
 * @return
 *   The translated string.
 */
I18nJs.t = function (str, args, options) {
	options = options || {};
	options.context = options.context || '';

	// Fetch the localized version of the string.
	if (I18nJs.locale.strings && I18nJs.locale.strings[options.context] && I18nJs.locale.strings[options.context][str]) {
		str = I18nJs.locale.strings[options.context][str];
	}

	if (args) {
		str = I18nJs.formatString(str, args);
	}
	return str;
};

/**
 * Format a string containing a count of items.
 *
 * This function ensures that the string is pluralized correctly. Since I18nJs.t() is
 * called by this function, make sure not to pass already-localized strings to it.
 *
 * See the documentation of the server-side format_plural() function for further details.
 *
 * @param count
 *   The item count to display.
 * @param singular
 *   The string for the singular case. Please make sure it is clear this is
 *   singular, to ease translation (e.g. use "1 new comment" instead of "1 new").
 *   Do not use @count in the singular string.
 * @param plural
 *   The string for the plural case. Please make sure it is clear this is plural,
 *   to ease translation. Use @count in place of the item count, as in "@count
 *   new comments".
 * @param args
 *   An object of replacements pairs to make after translation. Incidences
 *   of any key in this array are replaced with the corresponding value.
 *   See I18nJs.formatString().
 *   Note that you do not need to include @count in this array.
 *   This replacement is done automatically for the plural case.
 * @param options
 *   The options to pass to the I18nJs.t() function.
 * @return
 *   A translated string.
 */
I18nJs.formatPlural = function (count, singular, plural, args, options) {
	args = args || {};
	args['@count'] = count;
	// Determine the index of the plural form.
	var index = I18nJs.locale.pluralFormula ? I18nJs.locale.pluralFormula(args['@count']) : ((args['@count'] === 1) ? 0 : 1);

	if (index === 0) {
		return I18nJs.t(singular, args, options);
	}
	else if (index === 1) {
		return I18nJs.t(plural, args, options);
	}
	else {
		args['@count[' + index + ']'] = args['@count'];
		delete args['@count'];
		return I18nJs.t(plural.replace('@count', '@count[' + index + ']'), args, options);
	}
};

/**
 * Encodes a Drupal path for use in a URL.
 *
 * For aesthetic reasons slashes are not escaped.
 */
I18nJs.encodePath = function (item) {
	return window.encodeURIComponent(item).replace(/%2F/g, '/');
};

/**
 * Generate the themed representation of a Drupal object.
 *
 * All requests for themed output must go through this function. It examines
 * the request and routes it to the appropriate theme function. If the current
 * theme does not provide an override function, the generic theme function is
 * called.
 *
 * For example, to retrieve the HTML for text that should be emphasized and
 * displayed as a placeholder inside a sentence, call
 * I18nJs.theme('placeholder', text).
 *
 * @param func
 *   The name of the theme function to call.
 * @param ...
 *   Additional arguments to pass along to the theme function.
 * @return
 *   Any data the theme function returns. This could be a plain HTML string,
 *   but also a complex object.
 */
I18nJs.theme = function (func) {
	var args = Array.prototype.slice.apply(arguments, [1]);
	if (func in I18nJs.theme) {
		return I18nJs.theme[func].apply(this, args);
	}
};

/**
 * Formats text for emphasized display in a placeholder inside a sentence.
 *
 * @param str
 *   The text to format (plain-text).
 * @return
 *   The formatted text (html).
 */
I18nJs.theme.placeholder = function (str) {
	return '<em class="placeholder">' + I18nJs.checkPlain(str) + '</em>';
};
