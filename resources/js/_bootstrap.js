import axios from 'axios';
import 'bootstrap';
import.meta.glob( [
    '../images/**',
    '../fonts/**',
] );

window.axios = axios;

window.axios.defaults.headers.common[ 'X-Requested-With' ] = 'XMLHttpRequest';
