export default (el, opts) => {
  if (typeof el === 'string') {
    el = document.querySelector(el);
  }
  const options = Object.assign(
    {},
    {
      value() {},
      attribute() {},
    },
    opts
  );

  let ignore = false;

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      const type = mutation.type;
      
      if (ignore || type != 'attributes') {
        return;
      }
      const name = mutation.attributeName;
      const value = mutation.target.getAttribute(mutation.attributeName);
      const oldValue = mutation.oldValue;
      const attributeFuncName = '$' + name;

      ignore = true;

      options.attribute.call(el, name, value, oldValue);
      options[attributeFuncName] && options[attributeFuncName].call(el, value, oldValue);

      ignore = false;
    });
  });

  observer.observe(el, {
    attributes: true,
    attributeoldvalue: true
  });

  el.addEventListener('input', function(e) {
    options.value(e.target.value, e);
  }, false);

  return observer;
};