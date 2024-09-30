import ref from './ref.js';
import observe from './observe.js';

export default (el) => {
  let ignored = {};

  if (typeof el === 'string') {
    el = document.querySelector(el);
  }
  const lower = function(name) {
    return name == null ? '' : ('' + name).toLowerCase();
  };

  const ignore = function(name, value) {
    const lowerName = lower(name[0] == '$' ? name.substr(0) : name);

    if (typeof value === 'undefined') {
      return !!ignored[lowerName] || ('' + value).match(/^\$\$/);
    }
    if (value === false) {
      delete ignored[lowerName];

      return;
    }
    ignored[lowerName] = true;
  };

  const sync = ref({}, {
    set(name, value) {
      if (ignore(name)) {
        return;
      }
      if (name == 'value') {
        name = '$value';
      }
      if (name == 'innerHTML') {
        name = '$innerHTML';
      }
      if (name[0] == '$') {
        const attributeName = name.substr(1);

        ignore(name, true);

        el[attributeName] = value;

        setTimeout(() => ignore(name, false));

        return;
      }
      ignore(name, true);

      el.setAttribute(name, value);

      setTimeout(() => ignore(name, false));
    },

    get(prop, value, prim, ref) {
      if (value != null && typeof value !== 'undefined') {
        return value;
      }
      const property = el[prop];

      if (typeof property === 'function') {
        return property.bind(el);
      }
      if (typeof property !== 'undefined') {
        return property;
      }
      try {
        const attribute = el.getAttribute(prop);

        if (typeof attribute !== 'undefined') {
          return attribute;
        }
      } catch (e) {}

      try {
        const value = el[prop];

        if (typeof value !== 'undefined') {
          return value;
        }
      } catch (e) {}
    }
  });

  const observer = observe(el, {
    attribute(name, value) {
      if (ignore(name)) {
        return;
      }
      ignore(name, true);

      sync[name] = value;

      setTimeout(() => ignore(name, false));
    },

    value(value) {
      if (ignore('value')) {
        return;
      }
      ignore('value', true);

      sync.value = value;

      setTimeout(() => ignore('value', false));
    }
  });

  return sync;
};