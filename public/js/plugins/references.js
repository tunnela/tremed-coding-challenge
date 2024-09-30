import Plugin from '../framework/plugin.js';

class References extends Plugin {

  references = [];

  $beforeModuleSetup(mod, view) {
    let refs = {};

    view.querySelectorAll('[ref]').forEach((el) => {
      const name = el.getAttribute('ref');

      refs[name] = this.app.reactive(el);

      this.references.push(refs[name]);
    });

    mod.$ = mod.$refs = refs;
  }

  $destroy() {
    for (const ref of this.references) {
      ref.$destroy();
    }
  }
}

export default References;