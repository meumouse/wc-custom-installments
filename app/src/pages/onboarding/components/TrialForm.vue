<script setup>
/**
 * TrialForm.vue - ask the licensing service for a seven-day key.
 *
 * The exact request body is shown before it is sent. A consent notice that
 * describes a request in prose is a promise; showing the payload is the thing
 * itself, and it costs one disclosure element.
 *
 * @since 1.0.0
 */
import { computed, reactive, ref } from 'vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseInput from '../../../components/base/BaseInput.vue';
import { __, sprintf } from '../../../utils/i18n';

const props = defineProps({
  api: { type: Object, required: true },
  pro: { type: Object, default: () => ({}) },
  links: { type: Object, default: () => ({}) },
});

const form = reactive({ first_name: '', last_name: '', email: '', phone: '' });
const isSending = ref(false);
const error = ref(null);
const trial = ref(null);
const showPayload = ref(false);

const canSubmit = computed(() =>
  Object.values(form).every((value) => String(value).trim() !== '') && !isSending.value,
);

const payload = computed(() => JSON.stringify({ productId: '…', ...form }, null, 2));

/**
 * Ask for the trial.
 *
 * @since 1.0.0
 * @return {Promise<void>}
 */
async function submit() {
  isSending.value = true;
  error.value = null;

  try {
    const response = await props.api.post('trial', { ...form });

    if (response.status === 'error') {
      error.value = response.message;

      return;
    }

    trial.value = response.trial;
  } catch (requestError) {
    error.value = requestError.message || __('That did not work. Please try again.');
  } finally {
    isSending.value = false;
  }
}
</script>

<template>
  <div v-if="pro.active" class="rounded-xl border border-shell-100 bg-shell-50 p-5 text-sm text-muted">
    {{ __('The Pro add-on is already installed on this site.') }}
  </div>

  <div v-else-if="trial" class="rounded-xl border border-success/30 bg-success/5 p-5">
    <p class="m-0 text-sm font-semibold text-ink">{{ __('Your trial is ready.') }}</p>

    <p class="mt-1 text-sm text-muted">
      {{ __('A copy is on its way to your inbox. Install the Pro add-on and paste this key into its licence screen.') }}
    </p>

    <code class="mt-3 block break-all rounded-lg bg-white px-3 py-2 font-mono text-sm text-ink">
      {{ trial.license_key }}
    </code>

    <a
      :href="pro.store_url"
      target="_blank"
      rel="noopener noreferrer"
      class="mt-3 inline-block text-sm font-semibold text-primary"
    >
      {{ __('Download the Pro add-on') }}
    </a>
  </div>

  <!-- With no product configured there is nothing to ask for, so the step
       offers a link and sends nothing. -->
  <div v-else-if="!pro.trial_available" class="rounded-xl border border-shell-100 bg-shell-50 p-5">
    <p class="m-0 text-sm text-muted">
      {{ __('Pro adds discounts per payment method, tiered discounts, extra badges and full design control.') }}
    </p>

    <a
      :href="pro.store_url"
      target="_blank"
      rel="noopener noreferrer"
      class="mt-3 inline-block rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white no-underline hover:bg-primary-800"
    >
      {{ __('See what Pro adds') }}
    </a>
  </div>

  <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
    <p class="m-0 text-sm text-muted">
      {{ sprintf(__('Try every Pro feature for %d days. No card, and nothing renews on its own.'), pro.trial_days) }}
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
      <label class="flex flex-col gap-1 text-sm font-medium text-ink">
        {{ __('First name') }}
        <BaseInput v-model="form.first_name" :aria-label="__('First name')" />
      </label>

      <label class="flex flex-col gap-1 text-sm font-medium text-ink">
        {{ __('Last name') }}
        <BaseInput v-model="form.last_name" :aria-label="__('Last name')" />
      </label>

      <label class="flex flex-col gap-1 text-sm font-medium text-ink">
        {{ __('E-mail') }}
        <BaseInput v-model="form.email" type="email" :aria-label="__('E-mail')" />
      </label>

      <label class="flex flex-col gap-1 text-sm font-medium text-ink">
        {{ __('Phone') }}
        <BaseInput v-model="form.phone" :aria-label="__('Phone')" />
      </label>
    </div>

    <div class="rounded-lg bg-shell-50 p-3 text-xs leading-relaxed text-muted">
      {{ sprintf(__('Pressing the button sends these four fields to %s to issue your key. Nothing has been sent yet, and skipping this step sends nothing at all.'), pro.api_host || 'the licensing service') }}

      <button type="button" class="ml-1 font-semibold text-primary underline" @click="showPayload = !showPayload">
        {{ showPayload ? __('Hide what is sent') : __('Show exactly what is sent') }}
      </button>

      <pre v-if="showPayload" class="mt-2 overflow-x-auto rounded bg-white p-2 font-mono text-[11px] text-ink">{{ payload }}</pre>

      <p class="mt-2">
        <a :href="links.terms_url" target="_blank" rel="noopener noreferrer" class="text-primary">{{ __('Terms') }}</a>
        ·
        <a :href="links.privacy_url" target="_blank" rel="noopener noreferrer" class="text-primary">{{ __('Privacy') }}</a>
      </p>
    </div>

    <p v-if="error" class="m-0 rounded-lg bg-danger/10 px-3 py-2 text-sm text-danger" role="alert">
      {{ error }}
    </p>

    <div>
      <BaseButton type="submit" :disabled="!canSubmit">
        {{ isSending ? __('Requesting…') : __('Start the free trial') }}
      </BaseButton>
    </div>
  </form>
</template>
