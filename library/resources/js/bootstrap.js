import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Send cookies (session + XSRF-TOKEN) on every request so the Sanctum
// stateful guard can authenticate /api/* calls from the SPA. Same-origin
// requests would send them anyway, but being explicit also makes any future
// cross-subdomain deployment (api.example.com) work without code changes.
window.axios.defaults.withCredentials = true;

// axios already defaults to xsrfCookieName: 'XSRF-TOKEN' and
// xsrfHeaderName: 'X-XSRF-TOKEN', which matches Laravel's encrypted-cookie
// CSRF setup — no additional wiring needed.
