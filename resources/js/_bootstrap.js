import axios from 'axios';
import 'bootstrap';

import.meta.glob( [
    '../images/**',
    '../fonts/**',
] );

window.axios = axios;

window.axios.defaults.headers.common[ 'X-Requested-With' ] = 'XMLHttpRequest';

/**
 * Handle "select all" checkboxes in tables
 */
document.addEventListener( 'click', e => {
    if ( !e.target.closest( '.select-all-checkbox' ) ) return;

    const table      = e.target.closest( 'table' );
    const checkboxes = table.querySelectorAll( 'tbody .form-check-input' );

    checkboxes.forEach( checkbox => checkbox.checked = e.target.checked );
} );
