export default (obj, ...forwards) => {
  const proxy = new Proxy(obj, {
    get(target, prop, receiver) {
      const value = obj[prop];

      if (value != null && typeof value !== 'undefined') {
        return value;
      }
      let found;

      forwards.forEach((forward) => {
        if (
          typeof found !== 'undefined' || 
          !forward || 
          typeof forward[prop] === 'undefined'
        ) {
          return;
        }
        found = forward[prop];

        if (typeof forward[prop] === 'function') {
          found = forward[prop].bind(forward);
        } else {
          found = forward[prop];
        }
      });

      return found;
    }
  });

  return proxy;
};