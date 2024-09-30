import Plugin from '../framework/plugin.js';

class Fetch extends Plugin {

  fetch(resource, options) {
    let headers = options?.headers || {};
    const user = this.app.getItem('user');
    const isAuth = options?.auth;

    if (user && (typeof isAuth === 'undefined' || isAuth)) {
      headers.Authorization = 'Bearer ' + user.access_token;
    }
    headers.Accept ||= 'application/json';
    headers['Content-Type'] ||= 'application/json';

    const opts = Object.assign({}, options || {}, {
      headers: headers
    });

    return fetch(resource, opts);
  }
}

export default Fetch;