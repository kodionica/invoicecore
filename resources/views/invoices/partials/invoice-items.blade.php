@php

    if(!empty(old())) {
//        dd(old());
    }

        $items = old('items', [['name' => '', 'description' => '', 'quantity' => '', 'price' => '']]);

@endphp

<div class="invoice__items columns-12">
    <h2 class="invoice-items__heading">Stavke fakture</h2>

    <div class="table-responsive">
        <table class="table">
            @foreach($items as $item)
                <tr>
                    <td>
                        <x-forms.input label="Usluga/Proizvod" name="items[{{ $loop->index }}][name]" required :value="$item['name']"/>
                    </td>
                    <td>
                        <x-forms.input label="Opis" name="items[{{ $loop->index }}][description]" :value="$item['description']"/>
                    </td>
                    <td>
                        <x-forms.input label="Količina" name="items[{{ $loop->index }}][quantity]" type="number" required :value="$item['quantity']"/>
                    </td>
                    <td>
                        <x-forms.input label="Cena" name="items[{{ $loop->index }}][price]" required :value="$item['price']"/>
                        <button type="button" class="btn btn-danger" data-invoice-action="remove">-</button>
                    </td>
                </tr>
            @endforeach
            <tr class="actions">
                <td colspan="3">
                    <button type="button" class="btn btn-primary" data-invoice-action="add">+</button>
                </td>
            </tr>
        </table>
    </div>
</div>

<template id="invoice-row-template">
    <td>
        <x-forms.input label="Usluga/Proizvod" name="items[{index}][name]" required/>
    </td>
    <td>
        <x-forms.input label="Opis" name="items[{index}][description]"/>
    </td>
    <td>
        <x-forms.input label="Količina" name="items[{index}][quantity]" type="number" required/>
    </td>
    <td>
        <x-forms.input label="Cena" name="items[{index}][price]" required/>
        <button type="button" class="btn btn-danger" data-invoice-action="remove">-</button>
    </td>
</template>
