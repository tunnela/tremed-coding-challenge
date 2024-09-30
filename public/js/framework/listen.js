export default (el) => {
  if (typeof el === 'string') {
    el = document.querySelector(el);
  }
  let controllers = [];

  return new Proxy({}, {
    get(target, prop) {
      if (prop == '$destroy') {
        return () => {
          controllers.forEach((controller) => controller.abort());
          controllers = [];
        };
      }
      return (handler) => {
        const controller = new AbortController();

        el.addEventListener(
          prop.replace(/^\$/, ''), 
          handler, 
          { signal: controller.signal }
        );

        controllers.push(controller);

        return () => controller.abort();
      };
    },
  });
};