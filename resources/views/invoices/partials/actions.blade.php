<div class="actions">
    <a href="{{ route('invoice.pdf', $invoice->id) }}" class="btn btn-outline-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round"
             data-lucide="printer" class="lucide lucide-printer me-2 icon-md">
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"></path>
            <rect x="6" y="14" width="12" height="8" rx="1"></rect>
        </svg>
        PDF</a>
    <a href="javascript:;" class="btn btn-outline-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round"
             data-lucide="printer" class="lucide lucide-printer me-2 icon-md">
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"></path>
            <rect x="6" y="14" width="12" height="8" rx="1"></rect>
        </svg>
        Štampaj</a>
    <a href="javascript:;" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round"
             data-lucide="send" class="lucide lucide-send me-3 icon-md">
            <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"></path>
            <path d="m21.854 2.147-10.94 10.939"></path>
        </svg>
        Pošalji fakturu</a>
</div>
