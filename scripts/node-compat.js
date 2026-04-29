const util = require('util');

if (typeof util.isRegExp !== 'function') {
    util.isRegExp = function isRegExp(value) {
        return Object.prototype.toString.call(value) === '[object RegExp]';
    };
}

if (typeof util.isDate !== 'function') {
    util.isDate = function isDate(value) {
        return Object.prototype.toString.call(value) === '[object Date]';
    };
}
