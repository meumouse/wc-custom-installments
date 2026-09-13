<script setup>
/**
 * TrialForm.vue - ask the licensing service for a seven-day key.
 *
 * The exact request body is shown before it is sent. A consent notice that
 * describes a request in prose is a promise; showing the payload is the thing
 * itself, and it costs one disclosure element.
 *
 * @since 6.0.0
 */
import { computed, reactive, ref } from 'vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseCode from '../../../components/base/BaseCode.vue';
import BaseForm from '../../../components/base/BaseForm.vue';
import BaseInput from '../../../components/base/BaseInput.vue';
import BaseLabel from '../../../components/base/BaseLabel.vue';
import BaseNotice from '../../../components/base/BaseNotice.vue';
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

// The four fields, described once: the form renders them from this list.
const FIELDS = [
  { key: 'first_name', label: () => __('First name'), type: 'text', autocomplete: 'given-name' },
  { key: 'last_name', label: () => __('Last name'), type: 'text', autocomplete: 'family-name' },
  { key: 'email', label: () => __('E-mail'), type: 'email', autocomplete: 'email' },
  { key: 'phone', label: () => __('Phone'), type: 'tel', autocomplete: 'tel' },
];

/**
 * Ask for the trial.
 *
 * @since 6.0.0
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
  <BaseNotice v-if="pro.active" tone="info">
    {{ __('The Pro add-on is already installed on this site.') }}
  </BaseNotice>

  <BaseNotice v-else-if="trial" tone="success" :title="__('Your trial is ready.')">
    <p>{{ __('A copy is on its way to your inbox. Install the Pro add-on and paste this key into its licence screen.') }}</p>

    <div class="mt-3">
      <BaseCode>{{ trial.license_key }}</BaseCode>
    </div>

    <template #actions>
      <BaseButton :href="pro.store_url" external icon="download">
        {{ __('Download the Pro add-on') }}
      </BaseButton>
    </template>
  </BaseNotice>

  <!-- With no product configured there is nothing to ask for, so the step
       offers a link and sends nothing. -->
  <BaseNotice v-else-if="!pro.trial_available" icon="crown">
    {{ __('Pro adds discounts per payment method, tiered discounts, extra badges and full design control.') }}

    <template #actions>
      <BaseButton :href="pro.store_url" external icon="crown">
        {{ __('See what Pro adds') }}
      </BaseButton>
    </template>
  </BaseNotice>

  <BaseForm v-else class="flex flex-col gap-5" @submit="submit">
    <p class="text-[14px] leading-6 text-slate-600">
      {{ sprintf(__('Try every Pro feature for %d days. No card, and nothing renews on its own.'), pro.trial_days) }}
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
      <div v-for="input in FIELDS" :key="input.key" class="flex flex-col gap-2">
        <BaseLabel :for="`wcci-trial-${input.key}`" size="sm">{{ input.label() }}</BaseLabel>
        <BaseInput
          :id="`wcci-trial-${input.key}`"
          v-model="form[input.key]"
          :type="input.type"
          :autocomplete="input.autocomplete"
          required
        />
      </div>
    </div>

    <BaseNotice icon="info-circle">
      {{ sprintf(__('Pressing the button sends these four fields to %s to issue your key. Nothing has been sent yet, and skipping this step sends nothing at all.'), pro.api_host || 'the licensing service') }}

      <div v-if="showPayload" class="mt-3">
        <BaseCode block>{{ payload }}</BaseCode>
      </div>

      <template #actions>
        <BaseButton variant="link" @click="showPayload = !showPayload">
          {{ showPayload ? __('Hide what is sent') : __('Show exactly what is sent') }}
        </BaseButton>
        <span class="text-slate-300" aria-hidden="true">·</span>
        <BaseButton variant="link" :href="links.terms_url" external>{{ __('Terms') }}</BaseButton>
        <span class="text-slate-300" aria-hidden="true">·</span>
        <BaseButton variant="link" :href="links.privacy_url" external>{{ __('Privacy') }}</BaseButton>
      </template>
    </BaseNotice>

    <BaseNotice v-if="error" tone="danger" live>
      {{ error }}
    </BaseNotice>

    <div>
      <BaseButton type="submit" icon="crown" :loading="isSending" :disabled="!canSubmit">
        {{ isSending ? __('Requesting…') : __('Start the free trial') }}
      </BaseButton>
    </div>
  </BaseForm>
</template>
