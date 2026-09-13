/**
 * onboarding.js — setup wizard entry.
 *
 * @since 6.0.0
 */
import '../styles/main.css';
import OnboardingPage from '../pages/onboarding/OnboardingPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('wc-custom-installments-onboarding-app', OnboardingPage);
