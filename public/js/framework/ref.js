export default (value, opts) => {
  const options = Object.assign(
    {},
    {
      set() {},
      get() {},
      run() {}
    },
    opts
  );

  const listen = function (func, target, prim, prop) {
    return function () {
      options.run(target, prim, prop, arguments);

      return func.apply(prim, arguments);
    };
  };

  const obj = { value: value };

  const proxy = new Proxy(obj, {
    get(target, prop, receiver) {
      const prim = Reflect.get(target, "value");
      const ref = prim[prop];
      const value =
        typeof ref === "function" ? listen(ref, target, prim, prop) : ref;

      const result = options.get.call(target, prop, value, prim, ref);

      if (typeof result !== 'undefined') {
        return result;
      }
      return value;
    },

    set(target, prop, value) {
      const prim = Reflect.get(target, "value");

      Reflect.set(prim, prop, value);

      options.set.call(target, prop, value, prim);

      return true;
    },
  });

  return proxy;
};