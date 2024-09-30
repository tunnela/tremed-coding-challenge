import Plugin from '../framework/plugin.js';
import listen from '../framework/listen.js';

class Router extends Plugin {

  routes = [];
  middlewares = [];
  onPopStateListener = null;

  constructor() {
    super();

    this.addEventListeners();
  }

  $beforeViewRender(element) {
    element.querySelectorAll('a[href]').forEach((el) => {
      el.addEventListener('click', (e) => {
        e.preventDefault();

        this.to(el.href);
      }, false);
    });
  }

  async run() {
    const target = this.app || this;

    if (typeof target.destroy === 'function') {
      await target.destroy();
    }
    let done = false;
    
    for (const route of this.routes) {
      if (done) {
        continue;
      }
      let matches;

      if ((matches = this.matches(route)) === false) {
        continue;
      }
      let content = await route.handler.call(target, target);

      if (content === false) {
        continue;
      }
      let middlewares = this.middlewares.sort((a, b) => {
        return a.priority - b.priority;
      });

      for (const middleware of middlewares) {
        const modifiedContent = await middleware.handler.call(target, route, target, content);

        if (modifiedContent != null) {
          content = modifiedContent;
        }
      }
      if (content === false) {
        done = true;

        continue;
      }
      done = true;

      if (typeof target.render === 'function') {
        await target.render(content);
      }
    };
  }

  to(url) {
    history.pushState({}, '', url);

    this.run();
  }

  route(path, handler, options = {}) {
    this.routes.push(
      Object.assign(
        {},
        options, 
        {
          regex: this.regexify(path),
          handler: handler
        }
      )
    );
  }

  middleware(handler, priority = 0, options = {}) {
    this.middlewares.push(
      Object.assign(
        {},
        options,
        {
          priority: 0,
          handler: handler
        }
      )
    );
  }

  regexify(path) {
    const trimmed = path.replace(/\/+$/, ''); 

    return trimmed.replace(
      /\{([a-z0-9_\-]+)\}/g, 
      (match, paramName) => {
        return `(?<${paramName}>[^/]+)`;
      }
    )
    .replace(/\*/g, '.*');
  }

  matches(route) {
    const requestUri = window.location.pathname.replace(/\/+$/, ''); 
    const routeRegex = new RegExp(`^${route.regex}$`); 
    const isMatch = routeRegex.test(requestUri); 
    const matches = requestUri.match(routeRegex);

    if (!isMatch) {
      return false;
    }
    return matches ? matches.groups : {};
  }

  addEventListeners() {
    if (this.onPopStateListener) {
      return;
    }
    this.onPopStateListener = listen(window).popstate(this.onPopState.bind(this));
  }

  onPopState() {
    this.run();
  }
}

export default Router;