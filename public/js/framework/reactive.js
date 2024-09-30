import sync from './sync.js';
import listen from './listen.js';
import forward from './forward.js';

export default (el) => {
  if (typeof el === 'string') {
    el = document.querySelector(el);
  }
  const syncEl = sync(el);
  const listenEl = listen(el);
  const proxy = forward(syncEl, listenEl, el);

  proxy.$$el = el;

  return proxy;
};