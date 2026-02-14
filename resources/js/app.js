import './_bootstrap';

window.addEventListener( 'load', () => {
    manageAddRemoveInvoiceItemRow();

    /**
     * Handle "select all" checkboxes in tables
     */
    document.addEventListener( 'click', e => {
        if ( !e.target.closest( '.select-all-checkbox' ) ) return;

        const table      = e.target.closest( 'table' );
        const checkboxes = table.querySelectorAll( 'tbody .form-check-input' );

        checkboxes.forEach( checkbox => checkbox.checked = e.target.checked );
    } );
} );

function manageAddRemoveInvoiceItemRow() {
    const template = document.getElementById( 'invoice-row-template' );

    document.addEventListener( 'click', e => {
        if ( !e.target.closest( '[data-invoice-action]' ) ) return;

        const invoice_action_btn = e.target.closest( '[data-invoice-action]' ),
              current_row        = invoice_action_btn.closest( 'tr' ),
              tbody              = invoice_action_btn.closest( 'tbody' );

        if ( invoice_action_btn.dataset.invoiceAction === 'add' ) {
            const clone   = document.importNode( template.content, true );
            const new_row = tbody.insertRow( current_row.rowIndex );
            new_row.appendChild( clone );
        } else if ( invoice_action_btn.dataset.invoiceAction === 'remove' ) {
            tbody.deleteRow( current_row.rowIndex );
        }
    } );
}
