@extends('layouts.app')

@section('title', 'Create New Order')
@section('breadcrumb', 'New Order')

@push('head')
<style>
.co-page {
    width: min(100% - 48px, 1440px);
    margin: 0 auto;
    padding: 28px 0 42px;
}

.co-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
}

.co-heading h1 {
    margin: 0;
    color: var(--fm-text);
    font-size: clamp(24px, 3vw, 30px);
    font-weight: 750;
    letter-spacing: -.035em;
}

.co-heading p {
    margin: 7px 0 0;
    color: var(--fm-muted);
    font-size: 13px;
}

.co-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(340px, .85fr);
    align-items: start;
    gap: 20px;
}

.co-panel {
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--fm-border);
    border-radius: 14px;
    background: var(--fm-surface);
    box-shadow: var(--fm-shadow);
}

.co-cart-panel {
    position: sticky;
    top: 20px;
}

.co-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.co-section-heading h2 {
    margin: 0;
    color: var(--fm-text);
    font-size: 17px;
    font-weight: 750;
}

.co-count {
    padding: 5px 10px;
    border: 1px solid var(--fm-border);
    border-radius: 20px;
    background: var(--fm-surface-soft);
    color: var(--fm-muted);
    font-size: 11px;
    font-weight: 650;
}

.co-search {
    position: relative;
    margin-bottom: 15px;
}

.co-search input {
    width: 100%;
    min-height: 44px;
    padding: 10px 14px 10px 40px;
    border: 1px solid var(--fm-border);
    border-radius: 9px;
    background: var(--fm-surface);
    color: var(--fm-text);
    font: inherit;
}

.co-search::before {
    content: "";
    position: absolute;
    left: 15px;
    top: 14px;
    width: 12px;
    height: 12px;
    border: 1.7px solid var(--fm-muted);
    border-radius: 50%;
}

.co-search::after {
    content: "";
    position: absolute;
    left: 27px;
    top: 27px;
    width: 6px;
    height: 1.7px;
    background: var(--fm-muted);
    transform: rotate(45deg);
}

.co-search input:focus,
.co-field:focus,
.co-select:focus,
.co-textarea:focus {
    outline: none;
    border-color: var(--fm-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--fm-primary) 16%, transparent);
}

.co-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}

.co-category {
    min-height: 34px;
    padding: 7px 13px;
    border: 1px solid var(--fm-border);
    border-radius: 20px;
    background: var(--fm-surface);
    color: var(--fm-muted);
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.co-category:hover,
.co-category.active {
    border-color: var(--fm-primary);
    background: color-mix(in srgb, var(--fm-primary) 13%, var(--fm-surface));
    color: var(--fm-primary-hover);
}

.co-products {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 13px;
}

.co-product {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: stretch;
    overflow: hidden;
    padding: 0;
    border: 1px solid var(--fm-border);
    border-radius: 11px;
    background: var(--fm-surface);
    color: var(--fm-text);
    cursor: pointer;
    text-align: left;
    transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
}

.co-product:hover {
    transform: translateY(-2px);
    border-color: var(--fm-primary);
    box-shadow: var(--fm-shadow);
}

.co-product-image,
.co-product-placeholder {
    display: grid;
    width: 100%;
    height: 125px;
    place-items: center;
    background: var(--fm-surface-soft);
    color: var(--fm-primary);
    font-size: 30px;
    font-weight: 750;
    object-fit: cover;
}

.co-product-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 6px;
    padding: 12px;
}

.co-product-name {
    color: var(--fm-text);
    font-size: 13px;
    font-weight: 750;
    line-height: 1.4;
}

.co-product-description {
    display: -webkit-box;
    overflow: hidden;
    color: var(--fm-muted);
    font-size: 11px;
    line-height: 1.5;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.co-product-price {
    margin-top: auto;
    padding-top: 7px;
    color: var(--fm-primary-hover);
    font-size: 14px;
    font-weight: 800;
}

.co-empty {
    grid-column: 1 / -1;
    padding: 35px 18px;
    border: 1px dashed var(--fm-border);
    border-radius: 10px;
    color: var(--fm-muted);
    text-align: center;
    font-size: 13px;
}

.co-cart-items {
    display: grid;
    gap: 12px;
    max-height: 360px;
    overflow-y: auto;
    padding-right: 3px;
}

.co-cart-empty {
    padding: 25px 15px;
    border: 1px dashed var(--fm-border);
    border-radius: 10px;
    color: var(--fm-muted);
    text-align: center;
    font-size: 12px;
}

.co-cart-item {
    padding: 13px;
    border: 1px solid var(--fm-border);
    border-radius: 10px;
    background: var(--fm-surface);
}

.co-cart-item-top {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.co-cart-item-name {
    color: var(--fm-text);
    font-size: 13px;
    font-weight: 750;
}

.co-cart-item-meta {
    margin-top: 3px;
    color: var(--fm-muted);
    font-size: 11px;
}

.co-cart-item-price {
    flex-shrink: 0;
    color: var(--fm-text);
    font-size: 12px;
    font-weight: 750;
}

.co-cart-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
}

.co-quantity {
    display: inline-flex;
    align-items: center;
    overflow: hidden;
    border: 1px solid var(--fm-border);
    border-radius: 7px;
}

.co-quantity button {
    width: 30px;
    height: 29px;
    border: 0;
    background: var(--fm-surface-soft);
    color: var(--fm-text);
    cursor: pointer;
    font-size: 15px;
}

.co-quantity span {
    min-width: 28px;
    color: var(--fm-text);
    text-align: center;
    font-size: 12px;
    font-weight: 700;
}

.co-remove {
    margin-left: auto;
    padding: 5px 8px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--fm-danger);
    cursor: pointer;
    font-size: 11px;
    font-weight: 650;
}

.co-remove:hover {
    background: color-mix(in srgb, var(--fm-danger) 10%, transparent);
}

.co-item-note {
    width: 100%;
    min-height: 35px;
    margin-top: 10px;
    padding: 7px 9px;
    border: 1px solid var(--fm-border);
    border-radius: 7px;
    background: var(--fm-surface);
    color: var(--fm-text);
    font-size: 11px;
}

.co-form-section {
    display: grid;
    gap: 12px;
    padding: 17px 0;
    border-top: 1px solid var(--fm-border);
}

.co-form-section h3 {
    margin: 0;
    color: var(--fm-text);
    font-size: 13px;
    font-weight: 750;
}

.co-order-types {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 7px;
}

.co-type-option {
    min-height: 39px;
    padding: 8px 5px;
    border: 1px solid var(--fm-border);
    border-radius: 8px;
    background: var(--fm-surface);
    color: var(--fm-muted);
    cursor: pointer;
    font-size: 11px;
    font-weight: 650;
}

.co-type-option:hover,
.co-type-option.active {
    border-color: var(--fm-primary);
    background: color-mix(in srgb, var(--fm-primary) 13%, var(--fm-surface));
    color: var(--fm-primary-hover);
}

.co-form-group {
    display: grid;
    gap: 6px;
}

.co-form-group label {
    color: var(--fm-text);
    font-size: 12px;
    font-weight: 650;
}

.co-field,
.co-select,
.co-textarea {
    width: 100%;
    min-height: 41px;
    padding: 9px 11px;
    border: 1px solid var(--fm-border);
    border-radius: 8px;
    background: var(--fm-surface);
    color: var(--fm-text);
    font: inherit;
    font-size: 12px;
}

.co-textarea {
    min-height: 75px;
    resize: vertical;
}

.co-muted {
    color: var(--fm-muted);
    font-size: 11px;
    line-height: 1.5;
}

.co-summary {
    display: grid;
    gap: 11px;
    padding-top: 17px;
    border-top: 1px solid var(--fm-border);
}

.co-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: var(--fm-muted);
    font-size: 12px;
}

.co-summary-row strong {
    color: var(--fm-text);
    font-weight: 700;
}

.co-summary-total {
    padding-top: 13px;
    border-top: 1px dashed var(--fm-border);
    color: var(--fm-text);
    font-size: 14px;
    font-weight: 750;
}

.co-summary-total strong {
    color: var(--fm-primary-hover);
    font-size: 19px;
}

.co-submit {
    width: 100%;
    min-height: 45px;
    border: 0;
    border-radius: 9px;
    background: var(--fm-primary);
    color: #fff;
    cursor: pointer;
    font-size: 13px;
    font-weight: 750;
    transition: background .18s ease;
}

.co-submit:hover {
    background: var(--fm-primary-hover);
}

.co-submit:disabled {
    cursor: not-allowed;
    opacity: .5;
}

.co-modal-backdrop {
    position: fixed;
    z-index: 1200;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(20, 25, 21, .58);
    backdrop-filter: blur(3px);
}

.co-modal-backdrop.open {
    display: flex;
}

.co-modal {
    width: min(100%, 470px);
    max-height: min(85vh, 680px);
    overflow-y: auto;
    padding: 23px;
    border: 1px solid var(--fm-border);
    border-radius: 14px;
    background: var(--fm-surface);
    box-shadow: 0 18px 55px rgba(0, 0, 0, .2);
}

.co-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
}

.co-modal-header h2 {
    margin: 0;
    color: var(--fm-text);
    font-size: 18px;
    font-weight: 750;
}

.co-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    margin-top: 22px;
}

.co-modal-btn {
    min-height: 38px;
    padding: 8px 15px;
    border: 1px solid var(--fm-border);
    border-radius: 8px;
    background: var(--fm-surface);
    color: var(--fm-text);
    cursor: pointer;
    font-size: 12px;
    font-weight: 650;
}

.co-modal-btn-primary {
    border-color: var(--fm-primary);
    background: var(--fm-primary);
    color: #fff;
}

.co-modal-btn-primary:hover {
    background: var(--fm-primary-hover);
}

.co-choice-list {
    display: grid;
    gap: 9px;
}

.co-choice-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px;
    border: 1px solid var(--fm-border);
    border-radius: 8px;
    color: var(--fm-text);
    font-size: 12px;
}

.co-choice-row label {
    display: flex;
    align-items: center;
    gap: 8px;
}

.co-choice-price {
    color: var(--fm-primary-hover);
    font-weight: 700;
}


@media (max-width: 1100px) {
    .co-workspace {
        grid-template-columns: minmax(0, 1.2fr) minmax(310px, .9fr);
        gap: 14px;
    }

    .co-products {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .co-panel {
        padding: 17px;
    }
}

@media (max-width: 760px) {
    .co-page {
        width: min(100% - 30px, 1440px);
        padding-top: 20px;
    }

    .co-heading {
        flex-direction: column;
        gap: 10px;
    }

    .co-workspace {
        grid-template-columns: 1fr;
    }

    .co-cart-panel {
        position: static;
    }

    .co-products {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .co-product-image,
    .co-product-placeholder {
        height: 110px;
    }
}

@media (max-width: 420px) {
    .co-products {
        grid-template-columns: 1fr 1fr;
        gap: 9px;
    }

    .co-panel {
        padding: 13px;
    }

    .co-product-image,
    .co-product-placeholder {
        height: 90px;
    }

    .co-product-body {
        padding: 9px;
    }

    .co-order-types {
        gap: 5px;
    }

    .co-type-option {
        font-size: 10px;
    }

    .co-modal {
        padding: 17px;
    }
}
/* FoodMetrics dynamic markup compatibility */
.co-products .product-card {
    display: block;
    width: 100%;
    padding: 11px;
    border: 1px solid var(--fm-border);
    border-radius: 11px;
    background: var(--fm-surface);
    color: var(--fm-text);
    cursor: pointer;
    text-align: left;
    transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
}

.co-products .product-card:hover {
    transform: translateY(-2px);
    border-color: var(--fm-primary);
    box-shadow: var(--fm-shadow);
}

.co-products .product-image {
    display: block;
    width: 100%;
    height: 120px;
    margin-bottom: 11px;
    border-radius: 9px;
    background: var(--fm-surface-soft);
    object-fit: cover;
}

.co-products .product-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 120px;
    margin-bottom: 11px;
    border-radius: 9px;
    background: var(--fm-surface-soft);
    color: var(--fm-primary-hover);
    font-size: 25px;
    font-weight: 800;
}

.co-products .product-name {
    margin-bottom: 5px;
    color: var(--fm-text);
    font-size: 13px;
    font-weight: 800;
    line-height: 1.4;
}

.co-products .product-description {
    min-height: 30px;
    margin-bottom: 9px;
    color: var(--fm-muted);
    font-size: 11px;
    line-height: 1.4;
}

.co-products .product-price {
    color: var(--fm-primary-hover);
    font-size: 14px;
    font-weight: 800;
}

.co-products .empty-menu {
    grid-column: 1 / -1;
    padding: 35px 15px;
    color: var(--fm-muted);
    text-align: center;
    font-size: 13px;
}

.co-cart-items .cart-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--fm-border);
}

.co-cart-items .cart-item:last-child {
    border-bottom: 0;
}

.co-cart-items .cart-item-top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.co-cart-items .cart-item-name {
    color: var(--fm-text);
    font-size: 12px;
    font-weight: 800;
}

.co-cart-items .cart-item-meta {
    margin-top: 4px;
    color: var(--fm-muted);
    font-size: 11px;
}

.co-cart-items .cart-item-price {
    flex-shrink: 0;
    color: var(--fm-text);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.co-cart-items .cart-item-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-top: 9px;
}

.co-cart-items .quantity-control {
    display: flex;
    align-items: center;
    gap: 9px;
}

.co-cart-items .quantity-control button {
    min-width: 28px;
    min-height: 28px;
    border: 1px solid var(--fm-border);
    border-radius: 6px;
    background: var(--fm-surface-soft);
    color: var(--fm-text);
    cursor: pointer;
}

.co-cart-items .quantity-control span {
    color: var(--fm-text);
    font-size: 12px;
    font-weight: 700;
}

.co-cart-items .remove-btn {
    padding: 6px 8px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--fm-danger);
    cursor: pointer;
    font-size: 11px;
}

.co-cart-items .remove-btn:hover {
    background: color-mix(in srgb, var(--fm-danger) 10%, transparent);
}

.co-cart-items .item-note {
    width: 100%;
    min-height: 34px;
    margin-top: 8px;
    padding: 7px 9px;
    border: 1px solid var(--fm-border);
    border-radius: 7px;
    background: var(--fm-surface);
    color: var(--fm-text);
    font-size: 11px;
}

.co-choice-list .choice-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 11px;
    border: 1px solid var(--fm-border);
    border-radius: 9px;
    color: var(--fm-text);
    font-size: 12px;
}

.co-choice-list .choice-price {
    color: var(--fm-primary-hover);
    font-weight: 700;
    white-space: nowrap;
}

.co-modal .addon-checkbox {
    accent-color: var(--fm-primary);
}

.co-cart-panel .field {
    width: 100%;
    min-height: 41px;
    padding: 9px 11px;
    border: 1px solid var(--fm-border);
    border-radius: 8px;
    background: var(--fm-surface);
    color: var(--fm-text);
    font: inherit;
    font-size: 12px;
}

.co-modal .field,
.co-modal .select-field {
    width: 100%;
    min-height: 40px;
    padding: 9px 11px;
    border: 1px solid var(--fm-border);
    border-radius: 8px;
    background: var(--fm-surface);
    color: var(--fm-text);
    font-size: 12px;
}

.co-cart-panel .field:focus,
.co-modal .field:focus,
.co-modal .select-field:focus,
.co-cart-items .item-note:focus {
    outline: none;
    border-color: var(--fm-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--fm-primary) 16%, transparent);
}
</style>
@endpush

@section('content')
<div class="co-page">
    <div class="co-heading">
        <div>
            <h1>Create New Order</h1>
            <p>Select products, customize items, and review the order before submitting.</p>
        </div>
        <a href="{{ route('orders.index') }}" class="fm-btn fm-btn-secondary">
            Back to Orders
        </a>
    </div>


    <form id="orderForm" method="POST" action="{{ route('cashier.orders.store') }}">
        @csrf

        <div class="co-workspace">
            <section class="co-panel">
                <div class="co-section-heading">
                    <h2>Product Menu</h2>
                    <span class="co-count" id="productCount"></span>
                </div>

                <div class="co-search">
                    <input id="productSearch" type="search"
                           placeholder="Search products..."
                           autocomplete="off">
                </div>

                <div class="co-categories" id="categoryList">
                    <button type="button" class="co-category category-btn active"
                            data-category="all">All</button>

                    @foreach ($categories as $category)
                        <button type="button" class="co-category category-btn"
                                data-category="{{ $category->id }}">
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>

                <div class="co-products product-grid" id="productGrid"></div>
            </section>

            <aside class="co-panel co-cart-panel">
                <div class="co-section-heading">
                    <h2>Current Order</h2>
                    <span class="co-count" id="cartCount">0 items</span>
                </div>

                <div class="co-cart-items cart-items" id="cartItems">
                    <div class="co-cart-empty cart-empty">
                        Your cart is empty. Select a product to begin.
                    </div>
                </div>

                <div class="co-form-section">
                    <h3>Order Type</h3>

                    <div class="co-order-types order-types">
                        <button type="button" class="co-type-option type-option active"
                                data-type="takeout">Takeout</button>
                        <button type="button" class="co-type-option type-option"
                                data-type="dine_in">Dine-in</button>
                        <button type="button" class="co-type-option type-option"
                                data-type="delivery">Delivery</button>
                    </div>

                    <input type="hidden" name="order_type" id="orderType" value="takeout">
                </div>

                <div class="co-form-section" id="customerSection">
                    <h3>Customer</h3>
                    <div class="co-muted">Walk-in Customer</div>
                </div>


                <div class="co-form-section" id="deliverySection" hidden>
                    <h3>Delivery Customer Information</h3>

                    <div class="co-form-group">
                        <label for="recipientName">Recipient name *</label>
                        <input class="co-field field" id="recipientName"
                               name="recipient_name" maxlength="150"
                               placeholder="Full name" disabled>
                    </div>

                    <div class="co-form-group">
                        <label for="recipientPhone">Phone number *</label>
                        <input class="co-field field" id="recipientPhone"
                               name="recipient_phone" maxlength="30"
                               placeholder="Contact number" disabled>
                    </div>

                    <div class="co-form-group">
                        <label for="deliveryAddress">Delivery address *</label>
                        <textarea class="co-textarea textarea-field"
                                  id="deliveryAddress" name="delivery_address"
                                  maxlength="2000"
                                  placeholder="Complete delivery address" disabled></textarea>
                    </div>

                    <div class="co-form-group">
                        <label for="deliveryInstructions">Delivery instructions</label>
                        <textarea class="co-textarea textarea-field"
                                  id="deliveryInstructions"
                                  name="delivery_instructions" maxlength="2000"
                                  placeholder="Landmark or other instructions"
                                  disabled></textarea>
                    </div>

                    <p class="co-muted">Delivery fee is currently &#8369;0.00.</p>
                </div>

                <div class="co-form-section">
                    <h3>Promotion</h3>
                    <div class="co-form-group">
                        <label for="promotionCode">Promotion code</label>
                        <input class="co-field field" name="promotion_code"
                               id="promotionCode" list="promotionOptions"
                               maxlength="50" placeholder="Enter promotion code">
                        <datalist id="promotionOptions">
                            @foreach ($promotions as $promotion)
                                <option value="{{ $promotion->promotion_code }}">
                                    {{ $promotion->promotion_name }}
                                </option>
                            @endforeach
                        </datalist>
                    </div>
                    <p class="co-muted">
                        Promotion eligibility and discount are verified by the server.
                    </p>
                </div>

                <div class="co-form-section">
                    <h3>Order Notes</h3>
                    <div class="co-form-group">
                        <label for="orderNotes">Special instructions</label>
                        <textarea class="co-textarea textarea-field"
                                  id="orderNotes" name="notes" maxlength="2000"
                                  placeholder="Special instructions for this order..."></textarea>
                    </div>
                </div>

                <div class="co-summary">
                    <div class="co-summary-row">
                        <span>Subtotal</span>
                        <strong id="subtotalDisplay">&#8369;0.00</strong>
                    </div>

                    <div class="co-summary-row">
                        <span>Tax</span>
                        <strong>&#8369;0.00</strong>
                    </div>

                    <div class="co-summary-row">
                        <span>Delivery fee</span>
                        <strong>&#8369;0.00</strong>
                    </div>

                    <div class="co-summary-row co-summary-total summary-total">
                        <span>Estimated total</span>
                        <strong id="totalDisplay">&#8369;0.00</strong>
                    </div>

                    <p class="co-muted">
                        Final totals are calculated and validated by the server.
                    </p>

                    <button class="co-submit submit-btn" id="submitOrder"
                            type="submit" disabled>
                        Create Order
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

<div class="co-modal-backdrop modal-backdrop" id="productModal"
     role="dialog" aria-modal="true" aria-labelledby="modalProductName">
    <div class="co-modal modal">
        <div class="co-modal-header modal-header">
            <div>
                <h2 id="modalProductName">Customize product</h2>
                <div class="co-muted muted" id="modalProductPrice"></div>
            </div>
            <button type="button" class="co-modal-btn close-modal"
                    id="closeModal">Close</button>
        </div>

        <div id="variantSection" class="co-form-group form-group" hidden>
            <label for="variantSelect">Product variant</label>
            <select class="co-select select-field" id="variantSelect"></select>
        </div>

        <div id="addOnSection" class="co-form-group form-group" hidden>
            <label>Available add-ons</label>
            <div class="co-choice-list choice-list" id="addOnChoices"></div>
        </div>

        <div class="co-modal-actions modal-actions">
            <button type="button" class="co-modal-btn cancel-btn"
                    id="cancelModal">Cancel</button>
            <button type="button" class="co-modal-btn co-modal-btn-primary confirm-btn"
                    id="confirmProduct">Add to order</button>
        </div>
    </div>
</div>

<div class="co-modal-backdrop modal-backdrop" id="orderDialog"
     role="dialog" aria-modal="true"
     aria-labelledby="orderDialogTitle"
     aria-describedby="orderDialogMessage"
     aria-hidden="true">
    <div class="co-modal modal">
        <div class="co-modal-header modal-header">
            <div>
                <h2 id="orderDialogTitle">Confirm Order</h2>
                <div class="co-muted muted" id="orderDialogMessage"></div>
            </div>
        </div>

        <div class="co-modal-actions modal-actions">
            <button type="button" class="co-modal-btn cancel-btn"
                    id="orderDialogCancel">Cancel</button>
            <button type="button"
                    class="co-modal-btn co-modal-btn-primary confirm-btn"
                    id="orderDialogConfirm">Create Order</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
        const categories = {{ Illuminate\Support\Js::from($categories) }};
        const storageBase = @json(asset('storage'));

        const products = categories.flatMap(category =>
            (category.products || []).map(product => ({
                ...product,
                category_id: category.id,
                category_name: category.name,
                variants: product.variants || [],
                add_ons: product.add_ons || []
            }))
        );

        const peso = value => '₱' + Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[char]);

        let selectedCategory = 'all';
        let cart = [];
        let activeProduct = null;
        let lineCounter = 0;

        const productGrid = document.getElementById('productGrid');
        const searchInput = document.getElementById('productSearch');
        const cartItems = document.getElementById('cartItems');
        const productModal = document.getElementById('productModal');
        const variantSelect = document.getElementById('variantSelect');

        function renderProducts() {
            const term = searchInput.value.trim().toLowerCase();

            const filtered = products.filter(product => {
                const categoryMatch = selectedCategory === 'all' ||
                    String(product.category_id) === String(selectedCategory);
                const searchMatch = product.name.toLowerCase().includes(term) ||
                    String(product.description || '').toLowerCase().includes(term);
                return categoryMatch && searchMatch;
            });

            document.getElementById('productCount').textContent =
                `${filtered.length} available products`;

            if (!filtered.length) {
                productGrid.innerHTML = '<div class="empty-menu">No available products found.</div>';
                return;
            }

            productGrid.innerHTML = filtered.map(product => `
                <button type="button" class="product-card" data-product-id="${product.id}">
                    ${product.image
                        ? `<img class="product-image" src="${escapeHtml(storageBase + '/' + product.image)}" alt="${escapeHtml(product.name)}" loading="lazy">`
                        : `<div class="product-placeholder">${escapeHtml(product.name.charAt(0).toUpperCase())}</div>`}
                    <div class="product-name">${escapeHtml(product.name)}</div>
                    <div class="product-description">${escapeHtml(product.description || product.category_name || 'Available product')}</div>
                    <div class="product-price">${peso(product.base_price)}</div>
                </button>
            `).join('');
        }

        function openProductModal(product) {
            activeProduct = product;
            document.getElementById('modalProductName').textContent = product.name;
            document.getElementById('modalProductPrice').textContent =
                `Base price: ${peso(product.base_price)}`;

            const variants = product.variants.filter(item => item.is_available);
            const addOns = product.add_ons.filter(item => item.is_available);

            const variantSection = document.getElementById('variantSection');
            const addOnSection = document.getElementById('addOnSection');

            variantSection.hidden = variants.length === 0;
            addOnSection.hidden = addOns.length === 0;

            variantSelect.innerHTML = '<option value="">No variant</option>' +
                variants.map(variant => `
                    <option value="${variant.id}">
                        ${escapeHtml(variant.name)}
                        (${Number(variant.price_adjustment) >= 0 ? '+' : ''}${peso(variant.price_adjustment)})
                    </option>
                `).join('');

            document.getElementById('addOnChoices').innerHTML = addOns.map(addOn => `
                <div class="choice-row">
                    <label>
                        <input type="checkbox" value="${addOn.id}" class="addon-checkbox">
                        <span>${escapeHtml(addOn.name)}</span>
                    </label>
                    <span class="choice-price">${peso(addOn.price)}</span>
                </div>
            `).join('');

            productModal.classList.add('open');
        }

        function closeProductModal() {
            productModal.classList.remove('open');
            activeProduct = null;
        }

        function addProductToCart(product, variantId = '', addOnIds = []) {
            const variant = product.variants.find(item => String(item.id) === String(variantId));
            const selectedAddOns = product.add_ons
                .filter(item => addOnIds.includes(String(item.id)))
                .map(item => ({ id: Number(item.id), quantity: 1 }))
                .sort((a, b) => a.id - b.id);

            const key = [
                product.id,
                variant?.id || '',
                selectedAddOns.map(item => item.id).join(',')
            ].join(':');

            const existing = cart.find(item => item.key === key);

            if (existing) {
                if (existing.quantity < 99) existing.quantity++;
            } else {
                cart.push({
                    key,
                    line_id: ++lineCounter,
                    product_id: Number(product.id),
                    product_name: product.name,
                    base_price: Number(product.base_price),
                    variant_id: variant ? Number(variant.id) : '',
                    variant_name: variant?.name || '',
                    variant_adjustment: Number(variant?.price_adjustment || 0),
                    add_ons: selectedAddOns.map(addOn => {
                        const source = product.add_ons.find(item => Number(item.id) === addOn.id);
                        return {
                            id: addOn.id,
                            name: source.name,
                            price: Number(source.price),
                            quantity: 1
                        };
                    }),
                    quantity: 1,
                    notes: ''
                });
            }

            renderCart();
        }

        function lineUnitPrice(item) {
            return item.base_price + item.variant_adjustment +
                item.add_ons.reduce((sum, addOn) => sum + addOn.price * addOn.quantity, 0);
        }

        function renderCart() {
            const totalQuantity = cart.reduce((sum, item) => sum + item.quantity, 0);
            const subtotal = cart.reduce((sum, item) => sum + lineUnitPrice(item) * item.quantity, 0);

            document.getElementById('cartCount').textContent =
                `${totalQuantity} ${totalQuantity === 1 ? 'item' : 'items'}`;
            document.getElementById('subtotalDisplay').textContent = peso(subtotal);
            document.getElementById('totalDisplay').textContent = peso(subtotal);
            document.getElementById('submitOrder').disabled = cart.length === 0;

            if (!cart.length) {
                cartItems.innerHTML = '<div class="cart-empty">Your cart is empty. Select a product to begin.</div>';
                renderHiddenInputs();
                return;
            }

            cartItems.innerHTML = cart.map(item => `
                <div class="cart-item">
                    <div class="cart-item-top">
                        <div>
                            <div class="cart-item-name">${escapeHtml(item.product_name)}</div>
                            ${item.variant_name ? `<div class="cart-item-meta">Variant: ${escapeHtml(item.variant_name)}</div>` : ''}
                            ${item.add_ons.map(addOn =>
                                `<div class="cart-item-meta">+ ${escapeHtml(addOn.name)}</div>`
                            ).join('')}
                        </div>
                        <div class="cart-item-price">${peso(lineUnitPrice(item) * item.quantity)}</div>
                    </div>
                    <div class="cart-item-controls">
                        <div class="quantity-control">
                            <button type="button" data-action="decrease" data-id="${item.line_id}">−</button>
                            <span>${item.quantity}</span>
                            <button type="button" data-action="increase" data-id="${item.line_id}">+</button>
                        </div>
                        <button type="button" class="remove-btn" data-action="remove" data-id="${item.line_id}">Remove</button>
                    </div>
                    <input class="field item-note" data-note-id="${item.line_id}"
                           maxlength="500" placeholder="Item notes (optional)"
                           value="${escapeHtml(item.notes)}">
                </div>
            `).join('');

            renderHiddenInputs();
        }

        function renderHiddenInputs() {
            document.querySelectorAll('.generated-order-input').forEach(input => input.remove());

            const form = document.getElementById('orderForm');

            cart.forEach((item, index) => {
                addHiddenInput(form, `items[${index}][product_id]`, item.product_id);
                addHiddenInput(form, `items[${index}][quantity]`, item.quantity);
                addHiddenInput(form, `items[${index}][notes]`, item.notes);

                if (item.variant_id) {
                    addHiddenInput(form, `items[${index}][product_variant_id]`, item.variant_id);
                }

                item.add_ons.forEach((addOn, addOnIndex) => {
                    addHiddenInput(form,
                        `items[${index}][add_ons][${addOnIndex}][add_on_id]`, addOn.id);
                    addHiddenInput(form,
                        `items[${index}][add_ons][${addOnIndex}][quantity]`, addOn.quantity);
                });
            });
        }

        function addHiddenInput(form, name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            input.className = 'generated-order-input';
            form.appendChild(input);
        }

        productGrid.addEventListener('click', event => {
            const card = event.target.closest('[data-product-id]');
            if (!card) return;

            const product = products.find(item => String(item.id) === card.dataset.productId);
            if (!product) return;

            const hasChoices = product.variants.some(item => item.is_available) ||
                product.add_ons.some(item => item.is_available);

            if (hasChoices) {
                openProductModal(product);
            } else {
                addProductToCart(product);
            }
        });

        document.getElementById('confirmProduct').addEventListener('click', () => {
            if (!activeProduct) return;

            const variantId = variantSelect.value;
            const addOnIds = [...document.querySelectorAll('.addon-checkbox:checked')]
                .map(input => input.value);

            addProductToCart(activeProduct, variantId, addOnIds);
            closeProductModal();
        });

        document.getElementById('closeModal').addEventListener('click', closeProductModal);
        document.getElementById('cancelModal').addEventListener('click', closeProductModal);

        productModal.addEventListener('click', event => {
            if (event.target === productModal) closeProductModal();
        });

        cartItems.addEventListener('click', event => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const id = Number(button.dataset.id);
            const item = cart.find(entry => entry.line_id === id);
            if (!item) return;

            if (button.dataset.action === 'increase' && item.quantity < 99) item.quantity++;
            if (button.dataset.action === 'decrease') item.quantity--;
            if (button.dataset.action === 'remove' || item.quantity < 1) {
                cart = cart.filter(entry => entry.line_id !== id);
            }

            renderCart();
        });

        cartItems.addEventListener('input', event => {
            const input = event.target.closest('[data-note-id]');
            if (!input) return;

            const item = cart.find(entry => entry.line_id === Number(input.dataset.noteId));
            if (item) {
                item.notes = input.value;
                renderHiddenInputs();
            }
        });

        searchInput.addEventListener('input', renderProducts);

        document.getElementById('categoryList').addEventListener('click', event => {
            const button = event.target.closest('[data-category]');
            if (!button) return;

            selectedCategory = button.dataset.category;
            document.querySelectorAll('.category-btn').forEach(item =>
                item.classList.toggle('active', item === button)
            );
            renderProducts();
        });

        document.querySelectorAll('.type-option').forEach(button => {
            button.addEventListener('click', () => {
                const type = button.dataset.type;
                document.getElementById('orderType').value = type;

                document.querySelectorAll('.type-option').forEach(item =>
                    item.classList.toggle('active', item === button)
                );

                const customerSection = document.getElementById('customerSection');
                customerSection.hidden = type === 'delivery';

                const deliverySection = document.getElementById('deliverySection');

                deliverySection.hidden = type !== 'delivery';

                ['recipientName', 'recipientPhone', 'deliveryAddress', 'deliveryInstructions']
                    .forEach(id => {
                        const field = document.getElementById(id);
                        field.disabled = type !== 'delivery';
                        field.required = type === 'delivery' &&
                            ['recipientName', 'recipientPhone', 'deliveryAddress'].includes(id);
                    });
            });
        });

        const orderDialog = document.getElementById('orderDialog');
        const orderDialogTitle = document.getElementById('orderDialogTitle');
        const orderDialogMessage = document.getElementById('orderDialogMessage');
        const orderDialogCancel = document.getElementById('orderDialogCancel');
        const orderDialogConfirm = document.getElementById('orderDialogConfirm');
        const orderForm = document.getElementById('orderForm');

        let orderConfirmationPending = false;

        function openOrderDialog(title, message, showConfirm = false) {
            orderDialogTitle.textContent = title;
            orderDialogMessage.textContent = message;
            orderDialogConfirm.hidden = !showConfirm;
            orderDialogCancel.textContent = showConfirm ? 'Cancel' : 'Close';
            orderDialog.classList.add('open');
            orderDialog.setAttribute('aria-hidden', 'false');
        }

        function closeOrderDialog() {
            orderDialog.classList.remove('open');
            orderDialog.setAttribute('aria-hidden', 'true');
        }

        orderDialogCancel.addEventListener('click', () => {
            orderConfirmationPending = false;
            closeOrderDialog();
        });

        orderDialogConfirm.addEventListener('click', () => {
            if (!orderConfirmationPending) return;

            orderConfirmationPending = false;
            closeOrderDialog();

            orderForm.dataset.confirmed = 'true';
            orderForm.requestSubmit();
        });

        orderDialog.addEventListener('click', event => {
            if (event.target === orderDialog) {
                orderConfirmationPending = false;
                closeOrderDialog();
            }
        });

        orderForm.addEventListener('submit', event => {
            if (!cart.length) {
                event.preventDefault();
                openOrderDialog(
                    'Your cart is empty',
                    'Please add at least one product before creating an order.'
                );
                return;
            }

            if (orderForm.dataset.confirmed === 'true') {
                delete orderForm.dataset.confirmed;
                return;
            }

            event.preventDefault();

            const orderType = document.getElementById('orderType').value;
            const total = document.getElementById('totalDisplay').textContent;

            orderConfirmationPending = true;

            openOrderDialog(
                'Confirm Order',
                `Create this ${orderType.replace('_', ' ')} order with an estimated total of ${total}? Please review the order details first.`,
                true
            );
        });

        renderProducts();
        renderCart();
    </script>
@endpush