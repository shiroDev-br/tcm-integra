{{-- Cards dos cadastros com ocorrências e processos; `novo` destaca os encontrados hoje. --}}
<x-term-cards
    :termos="$termos"
    :novo="$novo"
    count-attribute="ocorrencias_count"
    singular="ocorrência encontrada"
    plural="ocorrências encontradas"
    secondary-attribute="processos_count"
    secondary-singular="processo citado"
    secondary-plural="processos citados"
    empty-description="Cadastre municípios ou empresas para acompanhar as ocorrências e os processos."
/>
