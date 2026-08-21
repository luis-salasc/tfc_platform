<?php

test('la página de aprendizaje se muestra correctamente', function () {
    $response = $this->get(route('aprendizaje'));

    $response
        ->assertOk()
        ->assertSee('The Fitness Club')
        ->assertSee('Nuestra primera vista creada con Laravel y Blade.');
});