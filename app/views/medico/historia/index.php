<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Historia clínica</h1>
        <p>Busca un paciente para ver su evolución cronológica y antecedentes.</p>
    </div>
</header>

<section class="card">
    <div class="card-head">
        <div>
            <span class="card-kicker">Paciente</span>
            <h2>Buscar en tu historia clínica</h2>
        </div>
        <span class="badge badge-muted" data-historia-count>Sin paciente seleccionado</span>
    </div>

    <div class="field">
        <label for="historia-search">Nombre o CI</label>
        <div class="input-wrap">
            <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
            <input id="historia-search" type="search" placeholder="Buscar paciente por nombre o CI…" autocomplete="off" data-historia-search>
        </div>
    </div>
    <div class="picker-results" data-historia-results hidden></div>
    <div class="picker-selected" data-historia-selected hidden></div>
    <input type="hidden" data-historia-id>
</section>

<section class="card" aria-labelledby="historia-profile-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Ficha</span>
            <h2 id="historia-profile-title">Datos del paciente</h2>
        </div>
    </div>
    <div data-historia-profile>
        <p class="text-muted">Selecciona un paciente para ver su ficha.</p>
    </div>
</section>

<section class="card" aria-labelledby="historia-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Evolución</span>
            <h2 id="historia-title">Atenciones registradas</h2>
        </div>
    </div>
    <div data-historia-timeline>
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-file"></use></svg></span>
            <h2>Selecciona un paciente</h2>
            <p>Busca por nombre o CI para consultar su evolución clínica.</p>
        </div>
    </div>
</section>
