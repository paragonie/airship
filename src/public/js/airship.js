/**
 *  CMS Airship - Global Javascript Routines
 */

window.Airship = {
    // Escaping contexts
    "E_HTML": "html",
    "E_HTML_ATTR": "html_attr",
    "E_URL": "url",

    // Filters (anythine that matches these regexes gets filtered out):
    "FILTER_ALPHA": /[^A-Za-z]+/,
    "FILTER_ALPHANUMERIC": /[^A-Za-z0-9]+/,
    "FILTER_ID": /[^A-Za-z0-9_\-]+/,
    "FILTER_NUMERIC": /[^0-9]+/,

    /**
     * @param x
     * @param {string} type
     * @returns {boolean}
     */
    "assertType": function(x, type) {
        if (typeof x !== type) {
            throw TypeError("Expected " + type + ", got " + (typeof x));
        }
        return true;
    },

    /**
     * @param {string} a
     * @returns {string}
     */
    "e": function (a) {
        if (arguments.length > 1) {
            return this.escape(a, arguments[1]);
        }
        return this.escape(a, Airship.E_HTML_ATTR);
    },

    /**
     * @param {string} input
     * @param {string} context Optional.
     * @returns {string}
     */
    "escape": function (input, context) {
        if (typeof context === 'undefined') {
            return '';
        }
        this.assertType(input, 'string');
        if (context === this.E_HTML) {
            return this.escapeHtmlContext(input);
        } else if (context === this.E_URL) {
            return this.escapeUrlContext(input);
        } else {
            return this.escapeHtmlAttributeContext(input);
        }
    },

    /**
     * Escape this string in an HTML context
     *
     * @param {string} input
     * @returns {string}
     */
    "escapeHtmlContext": function (input) {
        return input
            .replace(/&/g,  "&amp;")
            .replace(/\//g, "&#x2F;")
            .replace(/</g,  "&lt;")
            .replace(/>/g,  "&gt")
            .replace(/"/g,  "&quot;")
            .replace(/'/g,  "&#x27;")
        ;
    },

    /**
     * Escape this string in an HTML attribute context
     *
     * @param {string} input
     * @returns {string}
     */
    "escapeHtmlAttributeContext": function (input) {
        return this.escapeHtmlContext(input)
            .replace(/`/g,  "&#x60;")
            .replace(/=/g,  "&#x3D;")
        ;
    },

    /**
     * Escape this string in a URL context
     *
     * @param {string} input
     * @returns {string}
     */
    "escapeUrlContext": function (input) {
        this.assertType(input, 'string');
        return encodeURIComponent(input);
    },

    /**
     * Filter out certain characters from the input string.
     * This fails closed by returning an empty string.
     *
     * @param {string} input
     * @param {RegExp} filter_out
     * @returns {string}
     */
    "filter": function (input, filter_out) {
        if (typeof filter_out === 'undefined') {
            return '';
        }
        if (!(filter_out instanceof RegExp)) {
            return '';
        }
        this.assertType(input, 'string');
        return input.replace(filter_out, '');
    }
};
