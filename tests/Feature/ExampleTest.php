<?php

test('a raiz redireciona para o login', function () {
    $this->get(route('home'))->assertRedirect('/login');
});
