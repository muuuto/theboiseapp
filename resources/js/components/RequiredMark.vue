<!-- Red * after the label of a field that must be filled in (screen readers get aria-required on the field) -->
<template>
    <span class="ml-0.5 font-bold text-red-600" :title="t('Required')" aria-hidden="true">*</span>
</template>
