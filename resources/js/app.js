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
    // Use global variable to track the correct row index
    // if the user deletes a row after unsuccessfully submitting the form
    let global_row_index = 1;

    if ( template ) {
        document.addEventListener( 'click', e => {
            if ( !e.target.closest( '[data-invoice-action]' ) ) return;

            const invoice_action_btn = e.target.closest( '[data-invoice-action]' ),
                  current_row        = invoice_action_btn.closest( 'tr' ),
                  tbody              = invoice_action_btn.closest( 'tbody' );

            if ( invoice_action_btn.dataset.invoiceAction === 'add' ) {
                const template_html = template.innerHTML.replaceAll( '{index}', global_row_index );
                const new_row       = tbody.insertRow( current_row.rowIndex );
                new_row.innerHTML   = template_html;

                global_row_index++;
            } else if ( invoice_action_btn.dataset.invoiceAction === 'remove' ) {
                tbody.deleteRow( current_row.rowIndex );
            }
        } );
    }
}
