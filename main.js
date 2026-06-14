
document.addEventListener("DOMContentLoaded", () => {
    window.setTimeout(() => {
        const splash = document.getElementById("splash");
        if (!splash) return;
        splash.classList.add("splash-hidden");
        window.setTimeout(() => splash.remove(), 550);
    }, 1900);

    bindNavigation();
    bindCartButtons();
    bindCustomerModal();
    restoreCart();
});

const state = window.appState || {};
let cart = [];
let pendingCheckout = false;

function bindNavigation() {
    document.querySelectorAll("[data-view]").forEach((button) => {
        button.addEventListener("click", () => {
            document.querySelectorAll(".view-section").forEach((section) => section.classList.remove("active-view"));
            document.querySelectorAll("[data-view]").forEach((item) => item.classList.remove("active"));

            document.getElementById(`${button.dataset.view}-view`)?.classList.add("active-view");
            button.classList.add("active");
        });
    });

    document.querySelectorAll("[data-category]").forEach((button) => {
        if (!button.classList.contains("nav-item")) return;
        button.addEventListener("click", () => {
            document.querySelectorAll(".nav-item").forEach((item) => item.classList.remove("active"));
            button.classList.add("active");
            const category = button.dataset.category;

            document.querySelectorAll("#menu-view .menu-card").forEach((card) => {
                card.hidden = category !== "all" && card.dataset.category !== category;
            });
        });
    });
}

function bindCartButtons() {
    document.querySelectorAll("[data-add-item]").forEach((button) => {
        button.addEventListener("click", () => {
            addToCart({
                id: button.dataset.id,
                name: button.dataset.name,
                price: Number(button.dataset.price),
            });
        });
    });

    document.getElementById("cartToggle")?.addEventListener("click", () => {
        const details = document.getElementById("cartDetails");
        const chevron = document.getElementById("cartChevron");
        details?.classList.toggle("open");
        chevron?.classList.toggle("fa-chevron-up");
        chevron?.classList.toggle("fa-chevron-down");
    });

    document.getElementById("useReward")?.addEventListener("change", updateCartUI);
    document.getElementById("checkoutButton")?.addEventListener("click", handleCheckout);
}

function restoreCart() {
    try {
        const stored = JSON.parse(localStorage.getItem("shawarma_cart") || "[]");
        cart = Array.isArray(stored)
            ? stored.filter((item) => item && item.id && Number(item.quantity) > 0)
            : [];
    } catch {
        cart = [];
    }

    updateCartUI();
}

function persistCart() {
    localStorage.setItem("shawarma_cart", JSON.stringify(cart));
}

function addToCart(item) {
    const existing = cart.find((cartItem) => cartItem.id === item.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ ...item, quantity: 1 });
    }

    persistCart();
    updateCartUI();
    showToast(`تمت إضافة ${item.name}`);
}

function changeQuantity(id, amount) {
    const item = cart.find((cartItem) => cartItem.id === id);
    if (!item) return;

    item.quantity += amount;
    cart = cart.filter((cartItem) => cartItem.quantity > 0);
    persistCart();
    updateCartUI();
}

function totals() {
    const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const rewardAvailable = Number(state.user?.reward_count || 0) > 0;
    const rewardChecked = Boolean(document.getElementById("useReward")?.checked);
    const useReward = rewardAvailable && rewardChecked;
    const total = useReward ? subtotal * (1 - Number(state.reward_discount || 5) / 100) : subtotal;
    return { subtotal, total, useReward };
}

function updateCartUI() {
    const cartBar = document.getElementById("cartBar");
    const details = document.getElementById("cartDetails");
    const rewardOption = document.getElementById("rewardOption");
    if (!cartBar || !details) return;

    if (cart.length === 0) {
        cartBar.classList.remove("visible");
        return;
    }

    cartBar.classList.add("visible");
    const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    const calculated = totals();

    document.getElementById("cartCount").textContent = String(itemCount);
    document.getElementById("cartTotal").textContent = `${calculated.subtotal.toFixed(2)} ر.س`;
    document.getElementById("checkoutTotal").textContent = `${calculated.total.toFixed(2)} ر.س`;

    rewardOption?.classList.toggle("available", Number(state.user?.reward_count || 0) > 0);
    if (Number(state.user?.reward_count || 0) <= 0) {
        const checkbox = document.getElementById("useReward");
        if (checkbox) checkbox.checked = false;
    }

    details.innerHTML = cart.map((item) => `
        <div class="cart-item-row">
            <div>
                <strong>${escapeHtml(item.name)}</strong>
                <span>${(item.price * item.quantity).toFixed(2)} ر.س</span>
            </div>
            <div class="quantity-control">
                <button type="button" data-cart-id="${escapeHtml(item.id)}" data-change="1">+</button>
                <b>${item.quantity}</b>
                <button type="button" data-cart-id="${escapeHtml(item.id)}" data-change="-1">−</button>
            </div>
        </div>
    `).join("");

    details.querySelectorAll("[data-cart-id]").forEach((button) => {
        button.addEventListener("click", () => {
            changeQuantity(button.dataset.cartId, Number(button.dataset.change));
        });
    });
}

function bindCustomerModal() {
    document.getElementById("openLoginButton")?.addEventListener("click", () => openLoginModal(false));
    document.getElementById("closeLoginButton")?.addEventListener("click", closeLoginModal);
    document.getElementById("loginModal")?.addEventListener("click", (event) => {
        if (event.target.id === "loginModal") closeLoginModal();
    });
    document.getElementById("customerForm")?.addEventListener("submit", registerCustomer);
}

function openLoginModal(forCheckout = false) {
    pendingCheckout = forCheckout;
    const modal = document.getElementById("loginModal");
    modal?.classList.add("open");
    modal?.setAttribute("aria-hidden", "false");
    modal?.querySelector("input")?.focus();
}

function closeLoginModal() {
    const modal = document.getElementById("loginModal");
    modal?.classList.remove("open");
    modal?.setAttribute("aria-hidden", "true");
    pendingCheckout = false;
}

async function registerCustomer(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = document.getElementById("customerSubmit");
    const formData = new FormData(form);
    const shouldCheckout = pendingCheckout;

    setButtonBusy(submit, true, "جاري التفعيل...");
    try {
        const response = await fetch("customer_auth.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                csrf: state.csrf,
                name: formData.get("name"),
                phone: formData.get("phone"),
            }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || "تعذر تفعيل الحساب.");

        state.user = data.user;
        renderLoyaltyCard();
        closeLoginModal();
        updateCartUI();
        showToast(data.message);

        if (shouldCheckout) {
            await submitOrder();
        }
    } catch (error) {
        showToast(error.message, true);
    } finally {
        setButtonBusy(submit, false, "تفعيل الحساب");
    }
}

async function handleCheckout() {
    if (cart.length === 0) return;
    if (!state.user) {
        openLoginModal(true);
        return;
    }

    await submitOrder();
}

async function submitOrder() {
    const checkoutButton = document.getElementById("checkoutButton");
    const orderToken = crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;

    setButtonBusy(checkoutButton, true, "جاري حفظ الطلب...");

    try {
        const response = await fetch("process_order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                csrf: state.csrf,
                order_token: orderToken,
                use_reward: totals().useReward,
                items: cart.map((item) => ({ id: item.id, quantity: item.quantity })),
            }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || "تعذر حفظ الطلب.");

        state.user = data.user;
        cart = [];
        localStorage.removeItem("shawarma_cart");
        renderLoyaltyCard();
        updateCartUI();
        showToast(`تم حفظ الطلب #${data.order_id}. جاري فتح واتساب...`);
        window.setTimeout(() => {
            window.location.href = data.whatsapp_url;
        }, 350);
    } catch (error) {
        showToast(error.message, true);
    } finally {
        setButtonBusy(checkoutButton, false, "احفظ الطلب وأرسله عبر واتساب");
    }
}

function renderLoyaltyCard() {
    const shell = document.getElementById("loyaltyShell");
    if (!shell || !state.user) return;

    const progress = Math.min(100, Number(state.user.reward_progress || 0));
    const rewardCount = Number(state.user.reward_count || 0);
    const remaining = Math.max(0, Number(state.reward_threshold || 100) - Number(state.user.reward_progress || 0));
    const rewardStatus = rewardCount > 0
        ? `
            <div class="reward-summary reward-ready">
                <strong><i class="fa-solid fa-circle-check"></i> لديك ${rewardCount} مكافأة جاهزة</strong>
                <span>يمكنك تفعيل خصم 5% من السلة في طلبك القادم.</span>
            </div>
        `
        : `
            <div class="reward-summary reward-waiting">
                <strong><i class="fa-solid fa-hourglass-half"></i> لا توجد مكافأة جاهزة الآن</strong>
                <span>باقي <b>${remaining.toFixed(2)} ر.س</b> للحصول على خصم 5%.</span>
            </div>
        `;

    shell.innerHTML = `
        <div class="loyalty-card">
            <div class="user-summary">
                <i class="fa-solid fa-circle-user"></i>
                <div>
                    <span>يا هلا،</span>
                    <h2>${escapeHtml(state.user.name)}</h2>
                    <small>${escapeHtml(state.user.phone)}</small>
                </div>
            </div>
            ${rewardStatus}
            <div class="reward-progress">
                <div class="progress-copy">
                    <span>تقدم المكافأة القادمة</span>
                    <b>${Number(state.user.reward_progress || 0).toFixed(2)} / 100 ر.س</b>
                </div>
                <div class="progress-track"><span style="width:${progress}%"></span></div>
            </div>
            <a class="text-link" href="?logout=1">تسجيل الخروج</a>
        </div>
    `;
}

function setButtonBusy(button, busy, label) {
    if (!button) return;
    button.disabled = busy;
    button.innerHTML = busy
        ? `<i class="fa-solid fa-spinner fa-spin"></i> ${label}`
        : label.includes("واتساب")
            ? `<i class="fa-brands fa-whatsapp"></i> ${label}`
            : label;
}

function showToast(message, isError = false) {
    const toast = document.getElementById("toast");
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle("error", isError);
    toast.classList.add("show");
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => toast.classList.remove("show"), 3200);
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
    })[character]);
}

