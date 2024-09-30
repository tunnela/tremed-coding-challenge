import App from './framework/app.js';
import Router from './plugins/router.js';
import Storage from './plugins/storage.js';
import Fetch from './plugins/fetch.js';
import References from './plugins/references.js';

const router = new Router;

router.route('/', (app) => app.view('layout[users]'), { auth: true });

router.route('/login', (app) => app.view('login'), { auth: false });

router.route('/setup', (app) => app.view('setup'));

router.route('*', (app) => app.view('not-found'));

router.middleware(function(route) {
  let user = this.getItem('user');

  if (user && route.auth === false) {
    this.to('/');

    return false;
  } else if (!user && route.auth === true) {
    this.to('/login');

    return false;
  }
});

const app = new App([router, new Storage, new Fetch, new References]);

app.mount('#app');

app.run();