<x-layouts.layout>
    <x-page-header heading="Dodaj fakturu"/>

    @dump($active_company)

    <x-forms.form method="POST" action="{{ route('invoices.store') }}">
        <x-forms.select label="Valuta" name="currency" :value="$active_company->currency" :options="$currencies"/>
        <x-forms.select label="Klijent" name="client_id" :options="$clients"/>
        <x-forms.input label="Broj fakture" name="number" type="number" value="" required/>
        <x-forms.input label="Rok za isplatu (dana)" name="due_date" type="number" :value="$active_company->payment_due_days" required/>
        <x-forms.select label="Metod plaćanja" name="payment_method" :options="$payment_types"/>

        <div class="invoice__items columns-12">
            <h2 class="invoice-items__heading">Stavke fakture</h2>

            <div class="table-responsive">
                <table class="table">
                    <tr>
                        <td>
                            <x-forms.input label="Usluga/Proizvod" name="service[]" type="text" required/>
                        </td>
                        <td>
                            <x-forms.input label="Količina" name="quantity[]" type="number" required/>
                        </td>
                        <td>
                            <x-forms.input label="Cena" name="price[]" type="text" required/>
                            <button type="button" class="btn btn-danger" data-invoice-action="remove">-</button>
                        </td>
                    </tr>
                    <tr class="actions">
                        <td colspan="3">
                            <button type="button" class="btn btn-primary" data-invoice-action="add">+</button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="form-actions">
            <x-forms.button>Add Invoice</x-forms.button>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-danger">Cancel</a>
        </div>
    </x-forms.form>

    <template id="invoice-row-template">
        <td>
            <x-forms.input label="Service/Product" name="service[]" type="text" required/>
        </td>
        <td>
            <x-forms.input label="Quantity" name="quantity[]" type="number" required/>
        </td>
        <td>
            <x-forms.input label="Price" name="price[]" type="text" required/>
            <button type="button" class="btn btn-danger" data-invoice-action="remove">-</button>
        </td>
    </template>

</x-layouts.layout>
