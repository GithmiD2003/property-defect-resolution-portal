<?php

use Livewire\Component;

new class extends Component {
    public string $password = '';

    public function deleteUser(): void
    {
        abort(403, 'Account deletion is disabled. Contact a company manager about deactivation.');
    }
}; ?>

<div></div>