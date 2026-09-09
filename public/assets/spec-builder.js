(() => {
  const form = document.getElementById('spec-builder-form');
  if (!form) return;
  const budget = document.getElementById('spec-budget');
  const selected = document.getElementById('selected-parts');
  const filters = [...form.querySelectorAll('.brand-filter')];
  const money = value => `฿${Math.round(value).toLocaleString('th-TH')}`;
  const render = () => {
    const checked = [...form.querySelectorAll('.part-option input:checked')];
    let low = 0, total = 0, high = 0;
    selected.innerHTML = checked.length ? checked.map(input => {
      low += Number(input.dataset.low); total += Number(input.dataset.price); high += Number(input.dataset.high);
      return `<div class="summary-part"><span><small>${input.dataset.category}</small><b>${input.dataset.name}</b></span><strong>${money(input.dataset.price)}</strong></div>`;
    }).join('') : '<div class="summary-empty">ยังไม่ได้เลือกชิ้นส่วน</div>';
    document.getElementById('summary-low').textContent = money(low);
    document.getElementById('summary-total').textContent = money(total);
    document.getElementById('summary-high').textContent = money(high);
    const remaining = Number(budget.value || 0) - total;
    document.getElementById('summary-remaining').textContent = money(remaining);
    const state = document.getElementById('budget-state');
    state.className = `budget-state ${remaining >= 0 ? 'ok' : 'over'}`;
    state.innerHTML = `<span class="material-symbols-outlined">${remaining >= 0 ? 'check_circle' : 'warning'}</span><span>${remaining >= 0 ? 'อยู่ในงบประมาณ' : 'เกินงบประมาณ'}</span>`;
  };
  const filterParts = () => {
    const cpu = form.querySelector('input[name="parts[cpu]"]:checked');
    const cpuSocket = cpu?.dataset.socket || '';
    form.querySelectorAll('.part-options').forEach(group => {
      const key = group.dataset.group;
      const brand = form.querySelector(`.brand-filter[data-target="${key}"]`)?.value || '';
      group.querySelectorAll('.part-option').forEach(option => {
        const visible = (!brand || option.dataset.brand === brand) &&
          (key !== 'motherboard' || !cpuSocket || option.dataset.socket === cpuSocket);
        option.hidden = !visible;
        const input = option.querySelector('input');
        if (!visible && input.checked) input.checked = false;
      });
      const hint = group.closest('.component-section')?.querySelector('.socket-hint');
      if (hint) hint.textContent = cpuSocket ? `แสดงเฉพาะเมนบอร์ด Socket ${cpuSocket}` : 'เลือก CPU เพื่อกรอง Socket อัตโนมัติ';
    });
  };
  form.addEventListener('change', () => {
    filterParts();
    render();
  });
  filters.forEach(filter => filter.addEventListener('change', filterParts));
  budget.addEventListener('input', render);
  filterParts();
  render();
})();
