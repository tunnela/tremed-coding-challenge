import ref from './ref.js';
import sync from './sync.js';
import listen from './listen.js';
import observe from './observe.js';
import forward from './forward.js';
import reactive from './reactive.js';

class App {

  container = null;
  options = {};
  modules = [];
  containers = [];
  plugins = [];
  templates = {};

  constructor(container, plugins = [], options = {}) {
    if (Array.isArray(container)) {
      options = plugins;
      plugins = container;
      container = null;
    }
    this.mount(container);

    this.options = options;

    this.ref = ref;
    this.sync = sync;
    this.listen = listen;
    this.observe = observe;
    this.forward = forward;
    this.reactive = reactive;
    this.plugins = plugins;

    const proxy = forward(this, ...plugins);

    this.trigger('setup', proxy);

    return proxy;
  }

  mount(container) {
    this.container = typeof container === 'string' ? 
      document.querySelector(container) : container;
  }

  async render(content, slotName, container, props = {}, replace = true) {
    if (typeof slotName !== 'string') {
      replace = props;
      props = container;
      container = slotName;
      slotName = null;
    }
    container ||= this.container;

    const containerView = typeof container === 'string' ? 
      document.querySelector(container) : (container || this.container);
    const contentView = typeof content === 'string' ? 
      await this.view(content) : content;

    const slotSelector = slotName ? 
      'slot[name="' + slotName + '"]' : 'slot:not([name])';
    const slot = containerView.querySelector(slotSelector);

    if (slotName && !slot) {
      throw new Error('Slot `' + slotName + '` missing.');
    }
    if (!Array.isArray(contentView)) {
      this.trigger('beforeViewRender', contentView);
    }
    if (Array.isArray(contentView)) {
      for (const view of contentView) {
        const el = await this.render(
          view.content, 
          view.slot,
          containerView,
          props,
          replace
        );

        if (view.children.length) {
          await this.render(view.children, null, el, props, replace);
        }
      }
    } else if (slot) {
      slot.after(contentView);
    } else {
      if (replace) {
        containerView.innerHTML = '';
      }
      containerView.appendChild(contentView);
    }
    if (!Array.isArray(contentView)) {
      this.trigger('afterViewRender', contentView);

      contentView.querySelectorAll('script')
      .forEach(async (script) => {
        this.importScript(script, contentView, props);

        delete script.parentNode.removeChild(script);
      });

    }
    this.containers.push(containerView);

    return contentView;
  }

  trigger(event, ...args)
  {
    const name = '$' + event;

    this.plugins.forEach(
      (plugin) => plugin[name] && 
        plugin[name](...args)
    );
  }

  parseViews(str) {
    let views = [];
    let regex = /([^=\[]+)(=([^\[]+))?(\[([^\]]+)\])?/g;
    let parts;

    while ((parts = regex.exec(str)) !== null) {
      const template = parts[3] === undefined ? parts[1] : parts[3];
      const slot = parts[3] === undefined ? '' : parts[1];

      views.push({
        template: template,
        slot: slot,
        children: (parts[5] || '').split(',')
          .map((child) => child ? this.parseViews(child) : [])
          .flat()
          .filter((child) => child !== null)
      });
    }
    if (!views.length) {
      throw new Error('Invalid view string: ' + str);
    }
    return views;
  }

  async view(view) {
    const views = typeof view === 'string' ? 
      await this.parseViews(view) : view;

    for (const view of views) {
      let el = document.querySelector('template#' + view.template);

      if (!el) {
        throw new Error('Template `' + view.template + '` does not exist.');
      }
      if (el.hasAttribute('src')) {
        const src = el.getAttribute('src');
        let html;

        if (typeof this.templates[src] === 'undefined') {
          const response = await fetch(src);

          html = await response.text();

          this.templates[src] = html;
        } else {
          html = this.templates[src];
        }
        el = document.createElement('template');
        el.innerHTML = html;
      }
      const content = el.content.cloneNode(true);

      const wrapper = document.createElement('div');
      wrapper.style.display = 'contents';

      wrapper.appendChild(content);

      wrapper.querySelectorAll('script').forEach((el) => {
        el.setAttribute('type', 'ignore/javascript');
      });

      view.el = el;

      view.url = el.hasAttribute('src');

      view.content = wrapper;

      view.children = await this.view(view.children);
    }
    return views;
  }

  async importScript(script, view, props = {}) {
    // since we are using import() below, we need to manually
    // add support for relative URLs. Browser won't be
    // able to resolve them otherwise.
    const base = location.protocol + '//' + location.host;
    const code = script.innerHTML
    .replace(/from (["'])\//g, 'from $1' + base + '/')
    .replace(/from (["'])(\.{1,2})\//g, 'from $1' + base + '/$2/');

    const url = URL.createObjectURL(
      new Blob([code], { type: 'text/javascript' })
    );

    const mod = await import(url);

    if (typeof mod.default === 'function') {
      mod.default(this);
    } else if (typeof mod.default === 'object') {
      const proxy = forward(mod.default, this);

      this.trigger('beforeModuleSetup', mod.default, view);

      mod.default.$props = props;
      mod.default.$setup && proxy.$setup(this);

      this.trigger('afterModuleSetup', mod.default, view);

      this.modules.push(proxy);
    }
  }

  async destroy() {
    this.trigger('destroy');

    for (const mod of this.modules) {
      mod.hasOwnProperty('$destroy') && await mod.$destroy();
    }
    this.modules = [];

    this.containers.forEach((container) => {
      container.innerHTML = '';
    });

    this.containers = [];
  }
}

export default App;