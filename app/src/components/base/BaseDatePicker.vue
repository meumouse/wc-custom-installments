<script setup>
/**
 * BaseDatePicker.vue - a date, or a date and a time, picked from a calendar.
 *
 * Stands in for the browser's own date and time fields, which look different
 * in every browser and system and cannot wear the design system. The trigger
 * reads like BaseSelect's; the calendar opens in a panel teleported into the
 * portal, positioned against the trigger, so a card or the sticky save bar
 * never clips it.
 *
 * The value keeps the shape the native fields used, so nothing downstream
 * changes: `YYYY-MM-DD`, or `YYYY-MM-DDTHH:MM` with `withTime`. It is a wall
 * clock value - the store's time - and is never converted between timezones.
 *
 * Keyboard: in the calendar the arrows move by day and week, Home and End to
 * the ends of the week, Page Up and Page Down by month, Enter or Space picks,
 * and Escape closes. The hour and minute fields take the arrows too.
 *
 * @since 6.0.0
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import BaseIcon from './BaseIcon.vue';
import { ensurePortal } from '../../utils/portal';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: String, default: '' },
  // Adds the hour and minute, and keeps the panel open until "Concluir".
  withTime: { type: Boolean, default: false },
  // The time a day gets when it is picked with none set yet: an end of period
  // wants 23:59, a start 00:00.
  defaultTime: { type: String, default: '00:00' },
  // Earliest and latest days that can be picked, as `YYYY-MM-DD` (a time part is ignored).
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  id: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  clearable: { type: Boolean, default: true },
  invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const portal = ensurePortal();
const uid = `wcci-date-${Math.random().toString(36).slice(2, 9)}`;

const anchor = ref(null);
const trigger = ref(null);
const panel = ref(null);
const grid = ref(null);
const isOpen = ref(false);
const position = ref({ top: 0, left: 0, placement: 'bottom' });

const locale = computed(() => document.documentElement.lang || navigator.language || 'pt-BR');

/**
 * Two digits.
 *
 * @since 6.0.0
 * @param {number} value - A number below 100.
 * @return {string}
 */
function pad(value) {
  return String(value).padStart(2, '0');
}

/**
 * A day as `YYYY-MM-DD`, from its local fields.
 *
 * @since 6.0.0
 * @param {Date} date - The day.
 * @return {string}
 */
function toIso(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * Read a value into its day and time.
 *
 * @since 6.0.0
 * @param {string} value - `YYYY-MM-DD` or `YYYY-MM-DDTHH:MM`.
 * @return {{date: Date, hour: number, minute: number}|null}
 */
function parse(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?$/.exec(String(value || ''));

  if (!match) {
    return null;
  }

  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

  if (Number.isNaN(date.getTime()) || toIso(date) !== `${match[1]}-${match[2]}-${match[3]}`) {
    return null;
  }

  return { date, hour: Number(match[4] || 0), minute: Number(match[5] || 0) };
}

/**
 * Read `HH:MM`.
 *
 * @since 6.0.0
 * @param {string} value - A time.
 * @return {{hour: number, minute: number}}
 */
function parseTime(value) {
  const match = /^(\d{1,2}):(\d{2})$/.exec(String(value || ''));

  return match
    ? { hour: Math.min(23, Number(match[1])), minute: Math.min(59, Number(match[2])) }
    : { hour: 0, minute: 0 };
}

const selected = computed(() => parse(props.modelValue));
const minIso = computed(() => String(props.min || '').slice(0, 10));
const maxIso = computed(() => String(props.max || '').slice(0, 10));
const todayIso = toIso(new Date());

// The hour and minute being typed; they apply to the day as soon as they are valid.
const hour = ref('');
const minute = ref('');

/**
 * Copy the value's time into the fields, or the default time when there is none.
 *
 * @since 6.0.0
 * @return {void}
 */
function syncTime() {
  const time = selected.value && props.modelValue.includes('T') ? selected.value : parseTime(props.defaultTime);

  hour.value = pad(time.hour);
  minute.value = pad(time.minute);
}

syncTime();
watch(() => props.modelValue, syncTime);

// The month shown, and the day that has the keyboard.
const view = ref({ year: 0, month: 0 });
const focusIso = ref(todayIso);

const display = computed(() => {
  if (!selected.value) {
    return '';
  }

  const date = new Date(selected.value.date);

  if (props.withTime) {
    date.setHours(selected.value.hour, selected.value.minute);
  }

  return new Intl.DateTimeFormat(locale.value, props.withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(date);
});

const monthLabel = computed(() => {
  const label = new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }).format(
    new Date(view.value.year, view.value.month, 1),
  );

  return label.charAt(0).toUpperCase() + label.slice(1);
});

// 2023-01-01 was a Sunday: weeks start on Sunday, as Brazilian calendars do.
const weekdays = computed(() => {
  const short = new Intl.DateTimeFormat(locale.value, { weekday: 'short' });
  const long = new Intl.DateTimeFormat(locale.value, { weekday: 'long' });

  return Array.from({ length: 7 }, (_, index) => {
    const date = new Date(2023, 0, 1 + index);

    return { short: short.format(date).replace(/\.$/, ''), long: long.format(date) };
  });
});

const dayLabel = computed(() => new Intl.DateTimeFormat(locale.value, { dateStyle: 'full' }));

const weeks = computed(() => {
  const first = new Date(view.value.year, view.value.month, 1);
  const start = new Date(view.value.year, view.value.month, 1 - first.getDay());
  const selectedIso = selected.value ? toIso(selected.value.date) : '';
  const cells = [];

  for (let index = 0; index < 42; index += 1) {
    const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);
    const iso = toIso(date);

    cells.push({
      iso,
      day: date.getDate(),
      label: dayLabel.value.format(date),
      inMonth: date.getMonth() === view.value.month,
      isToday: iso === todayIso,
      isSelected: iso === selectedIso,
      // A boolean, never an empty string: Vue renders `disabled=""` as disabled.
      disabled: Boolean((minIso.value && iso < minIso.value) || (maxIso.value && iso > maxIso.value)),
    });
  }

  return Array.from({ length: 6 }, (_, week) => cells.slice(week * 7, week * 7 + 7));
});

/**
 * Show the month a day falls in.
 *
 * @since 6.0.0
 * @param {string} iso - `YYYY-MM-DD`.
 * @return {void}
 */
function showMonthOf(iso) {
  const date = parse(iso).date;

  view.value = { year: date.getFullYear(), month: date.getMonth() };
}

/**
 * Put the keyboard on a day, turning the month when it lies outside it.
 *
 * @since 6.0.0
 * @param {string} iso - `YYYY-MM-DD`.
 * @return {Promise<void>}
 */
async function focusDay(iso) {
  focusIso.value = iso;
  showMonthOf(iso);
  await nextTick();
  grid.value?.querySelector(`[data-iso="${iso}"]`)?.focus({ preventScroll: true });
}

/**
 * Write the value from a day and the time fields.
 *
 * @since 6.0.0
 * @param {string} iso - `YYYY-MM-DD`.
 * @return {void}
 */
function commit(iso) {
  if (!props.withTime) {
    emit('update:modelValue', iso);

    return;
  }

  const time = parseTime(`${hour.value || 0}:${pad(Math.min(59, Number(minute.value) || 0))}`);

  emit('update:modelValue', `${iso}T${pad(time.hour)}:${pad(time.minute)}`);
}

/**
 * Pick a day.
 *
 * @since 6.0.0
 * @param {object} cell - The calendar cell.
 * @return {void}
 */
function pick(cell) {
  if (cell.disabled) {
    return;
  }

  focusIso.value = cell.iso;
  commit(cell.iso);

  if (!props.withTime) {
    close(true);
  }
}

/**
 * Place the panel under the trigger, or above it when there is no room below.
 *
 * @since 6.0.0
 * @return {void}
 */
function place() {
  const rect = anchor.value?.getBoundingClientRect();

  if (!rect) {
    return;
  }

  const height = (panel.value?.offsetHeight || 400) + 8;
  const below = window.innerHeight - rect.bottom;
  const placement = below < height && rect.top > below ? 'top' : 'bottom';
  const width = panel.value?.offsetWidth || 304;

  position.value = {
    top: placement === 'bottom' ? rect.bottom + 6 : rect.top - 6,
    left: Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)),
    placement,
  };
}

/**
 * Open the calendar on the chosen day, or on today.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function open() {
  if (props.disabled || isOpen.value) {
    return;
  }

  syncTime();

  let start = selected.value ? toIso(selected.value.date) : todayIso;

  if (minIso.value && start < minIso.value) {
    start = minIso.value;
  }

  isOpen.value = true;
  place();
  await focusDay(start);
  place();
}

/**
 * Close the calendar.
 *
 * @since 6.0.0
 * @param {boolean} [refocus] - Whether to move focus back to the trigger.
 * @return {void}
 */
function close(refocus = false) {
  isOpen.value = false;

  if (refocus) {
    trigger.value?.focus({ preventScroll: true });
  }
}

/**
 * Move a number of months from the one shown.
 *
 * @since 6.0.0
 * @param {number} step - Months to move, negative for back.
 * @return {void}
 */
function turnMonth(step) {
  const date = new Date(view.value.year, view.value.month + step, 1);

  view.value = { year: date.getFullYear(), month: date.getMonth() };
}

/**
 * A day some days away from another.
 *
 * @since 6.0.0
 * @param {string} iso - `YYYY-MM-DD`.
 * @param {number} days - Days to add.
 * @param {number} [months] - Months to add.
 * @return {string}
 */
function shift(iso, days, months = 0) {
  const date = parse(iso).date;

  return toIso(new Date(date.getFullYear(), date.getMonth() + months, date.getDate() + days));
}

/**
 * Keys in the calendar grid.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onGridKeydown(event) {
  const day = parse(focusIso.value).date.getDay();
  const moves = {
    ArrowLeft: () => shift(focusIso.value, -1),
    ArrowRight: () => shift(focusIso.value, 1),
    ArrowUp: () => shift(focusIso.value, -7),
    ArrowDown: () => shift(focusIso.value, 7),
    Home: () => shift(focusIso.value, -day),
    End: () => shift(focusIso.value, 6 - day),
    PageUp: () => shift(focusIso.value, 0, -1),
    PageDown: () => shift(focusIso.value, 0, 1),
  };

  if (moves[event.key]) {
    event.preventDefault();
    focusDay(moves[event.key]());
  }
}

/**
 * Escape closes from anywhere in the panel.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onPanelKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close(true);
  }
}

/**
 * Close when focus leaves the panel for somewhere other than the trigger.
 *
 * @since 6.0.0
 * @param {FocusEvent} event - Focus event.
 * @return {void}
 */
function onPanelFocusout(event) {
  const next = event.relatedTarget;

  if (next && !panel.value?.contains(next) && !anchor.value?.contains(next)) {
    close(false);
  }
}

/**
 * Keep a time field to digits and apply it once it is complete.
 *
 * @since 6.0.0
 * @param {'hour'|'minute'} part - Which field.
 * @param {string} value - What was typed.
 * @return {void}
 */
function onTimeInput(part, value) {
  const digits = String(value).replace(/\D/g, '').slice(0, 2);

  (part === 'hour' ? hour : minute).value = digits;

  if (digits.length === 2 && selected.value) {
    commit(toIso(selected.value.date));
  }
}

/**
 * Tidy a time field when it is left: clamp it and pad it.
 *
 * @since 6.0.0
 * @param {'hour'|'minute'} part - Which field.
 * @return {void}
 */
function onTimeBlur(part) {
  const field = part === 'hour' ? hour : minute;

  field.value = pad(Math.min(part === 'hour' ? 23 : 59, Number(field.value) || 0));

  if (selected.value) {
    commit(toIso(selected.value.date));
  }
}

/**
 * The arrows step a time field, wrapping around.
 *
 * @since 6.0.0
 * @param {'hour'|'minute'} part - Which field.
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onTimeKeydown(part, event) {
  const step = { ArrowUp: 1, ArrowDown: -1 }[event.key];

  if (!step) {
    if (event.key === 'Enter') {
      event.preventDefault();
      onTimeBlur(part);
    }

    return;
  }

  event.preventDefault();

  const limit = part === 'hour' ? 24 : 60;
  const field = part === 'hour' ? hour : minute;

  field.value = pad(((Number(field.value) || 0) + step + limit) % limit);

  if (selected.value) {
    commit(toIso(selected.value.date));
  }
}

/**
 * Pick today, when it can be picked.
 *
 * @since 6.0.0
 * @return {void}
 */
function pickToday() {
  const cell = weeks.value.flat().find((item) => item.iso === todayIso);

  if (cell) {
    pick(cell);
  } else {
    focusDay(todayIso);
  }
}

/**
 * Empty the value.
 *
 * @since 6.0.0
 * @return {void}
 */
function clear() {
  emit('update:modelValue', '');
  close(true);
}

onClickOutside(panel, () => close(false), { ignore: [anchor] });

/**
 * Follow the trigger while the page scrolls or resizes under an open panel.
 *
 * @since 6.0.0
 * @return {void}
 */
function onViewportChange() {
  if (isOpen.value) {
    place();
  }
}

watch(isOpen, (value) => {
  const method = value ? 'addEventListener' : 'removeEventListener';

  window[method]('scroll', onViewportChange, true);
  window[method]('resize', onViewportChange);
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onViewportChange, true);
  window.removeEventListener('resize', onViewportChange);
});
</script>

<template>
  <div ref="anchor" class="relative w-full min-w-0">
    <button
      :id="id || undefined"
      ref="trigger"
      type="button"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      :aria-controls="`${uid}-panel`"
      :aria-label="ariaLabel ? (display ? `${ariaLabel}: ${display}` : ariaLabel) : undefined"
      :disabled="disabled"
      class="flex w-full min-w-0 items-center gap-3 rounded-[8px] border bg-white py-3 pl-4 text-left text-[14px] leading-5 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
      :class="[
        clearable && display ? 'pr-11' : 'pr-4',
        invalid ? 'border-rose-400' : isOpen ? 'border-primary ring-1 ring-primary' : 'border-slate-200',
        display ? 'text-slate-700' : 'text-slate-400',
      ]"
      @click="isOpen ? close(false) : open()"
    >
      <BaseIcon name="calendar" :size="18" class="text-slate-400" />
      <span class="truncate">
        {{ display || placeholder || (withTime ? __('Escolher data e hora') : __('Escolher data')) }}
      </span>
    </button>

    <button
      v-if="clearable && display && !disabled"
      type="button"
      class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-200"
      :aria-label="__('Limpar data')"
      :title="__('Limpar data')"
      @click="clear"
    >
      <BaseIcon name="x" :size="16" />
    </button>

    <Teleport :to="portal">
      <div
        v-if="isOpen"
        :id="`${uid}-panel`"
        ref="panel"
        role="dialog"
        :aria-label="ariaLabel || (withTime ? __('Escolher data e hora') : __('Escolher data'))"
        class="fixed z-[100070] w-[19rem] rounded-[8px] border border-slate-200 bg-white p-3 shadow-dropdown"
        :class="position.placement === 'top' ? '-translate-y-full' : ''"
        :style="{ top: `${position.top}px`, left: `${position.left}px` }"
        @keydown="onPanelKeydown"
        @focusout="onPanelFocusout"
      >
        <div class="mb-2 flex items-center justify-between gap-2">
          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-200"
            :aria-label="__('Mês anterior')"
            @click="turnMonth(-1)"
          >
            <BaseIcon name="chevron-left" :size="18" />
          </button>

          <span class="text-[14px] font-semibold text-slate-800" aria-live="polite">{{ monthLabel }}</span>

          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-200"
            :aria-label="__('Próximo mês')"
            @click="turnMonth(1)"
          >
            <BaseIcon name="chevron-right" :size="18" />
          </button>
        </div>

        <div ref="grid" role="grid" :aria-label="monthLabel" @keydown="onGridKeydown">
          <div role="row" class="grid grid-cols-7 gap-0.5">
            <span
              v-for="weekday in weekdays"
              :key="weekday.long"
              role="columnheader"
              :aria-label="weekday.long"
              class="flex h-7 items-center justify-center text-[11px] font-semibold uppercase text-slate-400"
            >
              {{ weekday.short }}
            </span>
          </div>

          <div v-for="(week, index) in weeks" :key="index" role="row" class="grid grid-cols-7 gap-0.5">
            <span v-for="cell in week" :key="cell.iso" role="gridcell" :aria-selected="cell.isSelected">
              <button
                type="button"
                :data-iso="cell.iso"
                :tabindex="cell.iso === focusIso ? 0 : -1"
                :disabled="cell.disabled"
                :aria-label="cell.label"
                :aria-current="cell.isToday ? 'date' : undefined"
                class="flex h-9 w-full items-center justify-center rounded-md text-[13px] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-300"
                :class="[
                  cell.isSelected
                    ? 'bg-primary-700 font-semibold text-white hover:bg-primary-800'
                    : cell.inMonth
                      ? 'text-slate-700 hover:bg-primary-50'
                      : 'text-slate-300 hover:bg-slate-50',
                  cell.isToday && !cell.isSelected ? 'font-semibold ring-1 ring-inset ring-primary-300' : '',
                  cell.disabled ? 'cursor-not-allowed opacity-40 hover:bg-transparent' : '',
                ]"
                @click="pick(cell)"
                @focus="focusIso = cell.iso"
              >
                {{ cell.day }}
              </button>
            </span>
          </div>
        </div>

        <div v-if="withTime" class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
          <span :id="`${uid}-time`" class="text-[13px] font-semibold text-slate-600">{{ __('Horário') }}</span>

          <div class="flex items-center gap-1.5" role="group" :aria-labelledby="`${uid}-time`">
            <input
              :value="hour"
              type="text"
              inputmode="numeric"
              maxlength="2"
              autocomplete="off"
              :aria-label="__('Hora')"
              class="w-12 rounded-md border border-slate-200 bg-white px-2 py-1.5 text-center text-[14px] tabular-nums text-slate-700 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
              @input="onTimeInput('hour', $event.target.value)"
              @blur="onTimeBlur('hour')"
              @keydown="onTimeKeydown('hour', $event)"
            />
            <span class="text-[14px] font-semibold text-slate-400" aria-hidden="true">:</span>
            <input
              :value="minute"
              type="text"
              inputmode="numeric"
              maxlength="2"
              autocomplete="off"
              :aria-label="__('Minutos')"
              class="w-12 rounded-md border border-slate-200 bg-white px-2 py-1.5 text-center text-[14px] tabular-nums text-slate-700 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
              @input="onTimeInput('minute', $event.target.value)"
              @blur="onTimeBlur('minute')"
              @keydown="onTimeKeydown('minute', $event)"
            />
          </div>
        </div>

        <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3">
          <button
            type="button"
            class="rounded-md px-2 py-1.5 text-[13px] font-semibold text-primary-700 transition-colors hover:bg-primary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-200"
            @click="pickToday"
          >
            {{ __('Hoje') }}
          </button>

          <div class="flex items-center gap-1">
            <button
              v-if="clearable && modelValue"
              type="button"
              class="rounded-md px-2 py-1.5 text-[13px] font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-200"
              @click="clear"
            >
              {{ __('Limpar') }}
            </button>

            <button
              v-if="withTime"
              type="button"
              class="rounded-md bg-primary-700 px-3 py-1.5 text-[13px] font-semibold text-white transition-colors hover:bg-primary-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100"
              @click="close(true)"
            >
              {{ __('Concluir') }}
            </button>
          </div>
        </div>

        <p v-if="withTime && !modelValue" class="mt-2 text-[12px] text-slate-400">
          {{ sprintf(__('Escolha um dia; o horário começa em %s.'), `${hour}:${minute}`) }}
        </p>
      </div>
    </Teleport>
  </div>
</template>
