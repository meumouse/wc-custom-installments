<script setup>
/**
 * SystemStatusCard.vue - the environment facts a support request needs.
 *
 * Read-only. The rows come from the server, each already judged healthy or
 * not, so the screen flags a low limit without knowing what the limit is.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseBadge from '../../../../components/base/BaseBadge.vue';
import { __ } from '../../../../utils/i18n';

const props = defineProps({
  rows: { type: Array, default: () => [] },
});

const groups = computed(() => {
  const result = [];

  props.rows.forEach((row) => {
    let group = result.find((one) => one.title === row.group);

    if (!group) {
      group = { title: row.group, rows: [] };
      result.push(group);
    }

    group.rows.push(row);
  });

  return result;
});
</script>

<template>
  <div class="grid gap-6 py-6 lg:grid-cols-3">
    <div v-for="group in groups" :key="group.title">
      <h3 class="text-[15px] font-semibold text-slate-800">{{ group.title }}</h3>

      <ul class="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
        <li v-for="row in group.rows" :key="row.label" class="flex flex-col gap-1 px-4 py-3">
          <div class="flex items-center justify-between gap-3">
            <span class="text-[13px] text-slate-600">{{ row.label }}</span>
            <BaseBadge :tone="row.ok ? 'success' : 'warning'" :icon="row.ok ? 'check' : 'alert-triangle'">
              {{ row.value }}
            </BaseBadge>
          </div>

          <span v-if="!row.ok && row.hint" class="text-[12px] text-amber-700">{{ row.hint }}</span>
        </li>
      </ul>
    </div>

    <p v-if="!groups.length" class="text-[13px] text-slate-500">
      {{ __('Não foi possível ler o status do sistema.') }}
    </p>
  </div>
</template>
