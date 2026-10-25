<?php
// Contact Us line icons follow the supplied design without an external library.
function eo_contact_icon($name)
{
    $icons = array(
        'mail'=>'<rect x="3.25" y="5.25" width="17.5" height="13.5" rx="2.7"/><path d="m4.25 7.25 7.75 5.6 7.75-5.6"/>',
        'phone'=>'<path d="M7.3 3.8 9.6 6a1.4 1.4 0 0 1 .3 1.5l-.9 1.7a14.7 14.7 0 0 0 5.8 5.8l1.7-.9a1.4 1.4 0 0 1 1.5.3l2.2 2.3a1.6 1.6 0 0 1 .2 1.9l-1 1.5a2.5 2.5 0 0 1-2.7 1C9.6 19.6 4.4 14.4 3 7.2a2.5 2.5 0 0 1 1-2.7l1.5-1a1.6 1.6 0 0 1 1.8.3Z"/>',
        'pin'=>'<path d="M19.25 10.1c0 5.2-7.25 10.9-7.25 10.9S4.75 15.3 4.75 10.1a7.25 7.25 0 0 1 14.5 0Z"/><circle cx="12" cy="10" r="2.4"/>',
        'clock'=>'<circle cx="12" cy="12" r="8.5"/><path d="M12 7.25v5.15l3.35 2"/>',
        'message'=>'<path d="M19.5 4.5H4.5A2.5 2.5 0 0 0 2 7v9a2.5 2.5 0 0 0 2.5 2.5H8l4 3 4-3h3.5A2.5 2.5 0 0 0 22 16V7a2.5 2.5 0 0 0-2.5-2.5Z"/><path d="M7.5 9h9m-9 4h6"/>',
        'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'external'=>'<path d="M14 3h7v7m0-7L10 14m0-11H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/>',
        'locate'=>'<circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2.5"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/>',
        'directions'=>'<path d="m3 11 18-8-8 18-2.9-7.1L3 11Z" fill="currentColor" stroke="none"/>'
    );
    if (!isset($icons[$name])) return '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$icons[$name].'</svg>';
}
