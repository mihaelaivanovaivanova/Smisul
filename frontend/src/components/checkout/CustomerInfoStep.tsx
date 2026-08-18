import FormField from '../FormField';
import PhoneField from '../PhoneField';
import { checkout as checkoutCopy } from '../../content/copy';
import type { CustomerInfo } from '../../types/checkout';

interface CustomerInfoStepProps {
  customer: CustomerInfo;
  onCustomerChange: <K extends keyof CustomerInfo>(field: K, value: CustomerInfo[K]) => void;
  errors: Record<string, string>;
}

export default function CustomerInfoStep({ customer, onCustomerChange, errors }: CustomerInfoStepProps) {
  return (
    <div>
      <div className="row">
        <div className="col-12 col-sm-6 mb-3">
          <FormField
            id="checkout-first-name"
            label={checkoutCopy.customer.firstName}
            value={customer.first_name}
            onChange={(value) => onCustomerChange('first_name', value)}
            error={errors['customer.first_name']}
            required
          />
        </div>
        <div className="col-12 col-sm-6 mb-3">
          <FormField
            id="checkout-last-name"
            label={checkoutCopy.customer.lastName}
            value={customer.last_name}
            onChange={(value) => onCustomerChange('last_name', value)}
            error={errors['customer.last_name']}
            required
          />
        </div>
      </div>

      <div className="mb-3">
        <FormField
          id="checkout-email"
          label={checkoutCopy.customer.email}
          type="email"
          value={customer.email}
          onChange={(value) => onCustomerChange('email', value)}
          error={errors['customer.email']}
          required
        />
      </div>

      <div className="mb-3">
        <PhoneField
          id="checkout-phone"
          label={checkoutCopy.customer.phone}
          value={customer.phone}
          onChange={(value) => onCustomerChange('phone', value)}
          error={errors['customer.phone']}
          required
        />
      </div>
    </div>
  );
}
