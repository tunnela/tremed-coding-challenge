import Plugin from '../framework/plugin.js';

class Storage extends Plugin {

  setItem(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
  }

  removeItem(key) {
    localStorage.removeItem(key);
  }

  getItem(key) {
    let item = localStorage.getItem(key);

    if (item == null) {
      return null;
    }
    if (typeof item === 'string' && ['[', '{'].includes(item[0])) {
      item = JSON.parse(item) || null;
    }
    return item;
  }
}

export default Storage;