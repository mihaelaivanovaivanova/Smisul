import FormField from '../FormField';
import { checkout as checkoutCopy } from '../../content/copy';
import type { CustomerInfo } from '../../types/checkout';

interface CustomerInfoStepProps {
  customer: CustomerInfo;
  deliveryNotes: string;
  onCustomerChange: <K extends keyof CustomerInfo>(field: K, value: CustomerInfo[K]) => void;
  onDeliveryNotesChange: (value: string) => void;
  errors: Record<string, string>;
}

export default function CustomerInfoStep({
  customer,
  deliveryNotes,
  onCustomerChange,
  onDeliveryNotesChange,
  errors,
}: CustomerInfoStepProps) {
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
        <FormField
          id="checkout-phone"
          label={checkoutCopy.customer.phone}
          type="tel"
          value={customer.phone}
          onChange={(value) => onCustomerChange('phone', value)}
          error={errors['customer.phone']}
          required
        />
      </div>

      <div className="row">
        <div className="col-12 col-sm-6 mb-3">
          <FormField
            id="checkout-company"
            label={checkoutCopy.customer.company}
            value={customer.company}
            onChange={(value) => onCustomerChange('company', value)}
            error={errors['customer.company']}
          />
        </div>
        <div className="col-12 col-sm-6 mb-3">
          <FormField
            id="checkout-vat-number"
            label={checkoutCopy.customer.vatNumber}
            value={customer.vat_number}
            onChange={(value) => onCustomerChange('vat_number', value)}
            error={errors['customer.vat_number']}
          />
        </div>
      </div>

      <div className="mb-3">
        <label htmlFor="checkout-delivery-notes" className="form-label">
          {checkoutCopy.customer.deliveryNotes}
        </label>
        <textarea
          id="checkout-delivery-notes"
          className={`form-control ${errors.delivery_notes ? 'is-invalid' : ''}`}
          rows={3}
          value={deliveryNotes}
          onChange={(event) => onDeliveryNotesChange(event.target.value)}
        />
      </div>
    </div>
  );
}
