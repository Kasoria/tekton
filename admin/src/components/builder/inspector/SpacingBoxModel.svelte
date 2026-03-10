<script>
  let { marginTop = '', marginRight = '', marginBottom = '', marginLeft = '', paddingTop = '', paddingRight = '', paddingBottom = '', paddingLeft = '', onStyleChange } = $props();

  function set(prop, value) {
    onStyleChange?.(prop, value);
  }
</script>

<div class="tk-box-model">
  <!-- Margin layer -->
  <div class="tk-box-margin">
    <span class="tk-box-label">margin</span>
    <input class="tk-box-input tk-box-top" value={marginTop} oninput={(e) => set('marginTop', e.target.value)} placeholder="0" />
    <input class="tk-box-input tk-box-right" value={marginRight} oninput={(e) => set('marginRight', e.target.value)} placeholder="0" />
    <input class="tk-box-input tk-box-bottom" value={marginBottom} oninput={(e) => set('marginBottom', e.target.value)} placeholder="0" />
    <input class="tk-box-input tk-box-left" value={marginLeft} oninput={(e) => set('marginLeft', e.target.value)} placeholder="0" />

    <!-- Padding layer (nested) -->
    <div class="tk-box-padding">
      <span class="tk-box-label">padding</span>
      <input class="tk-box-input tk-box-top" value={paddingTop} oninput={(e) => set('paddingTop', e.target.value)} placeholder="0" />
      <input class="tk-box-input tk-box-right" value={paddingRight} oninput={(e) => set('paddingRight', e.target.value)} placeholder="0" />
      <input class="tk-box-input tk-box-bottom" value={paddingBottom} oninput={(e) => set('paddingBottom', e.target.value)} placeholder="0" />
      <input class="tk-box-input tk-box-left" value={paddingLeft} oninput={(e) => set('paddingLeft', e.target.value)} placeholder="0" />

      <!-- Content center -->
      <div class="tk-box-content"></div>
    </div>
  </div>
</div>

<style>
  .tk-box-model {
    width: 100%;
  }

  .tk-box-margin, .tk-box-padding {
    position: relative;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    grid-template-rows: auto 1fr auto;
    min-height: 36px;
    border: 1px dashed var(--color-border);
    border-radius: 4px;
    padding: 2px;
  }

  .tk-box-margin {
    background: var(--color-card-hover);
  }

  .tk-box-padding {
    grid-column: 1 / -1;
    grid-row: 2;
    margin: 0 20px;
    background: var(--color-background);
    border-color: var(--color-copper, #c97d3c);
    border-style: dashed;
    opacity: 0.9;
  }

  .tk-box-content {
    grid-column: 2;
    grid-row: 2;
    min-width: 40px;
    min-height: 20px;
    background: var(--color-card);
    border: 1px solid var(--color-border);
    border-radius: 2px;
  }

  .tk-box-label {
    position: absolute;
    top: 1px;
    left: 4px;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--color-dim);
    pointer-events: none;
  }

  .tk-box-input {
    width: 36px;
    padding: 1px 2px;
    border: none;
    background: transparent;
    text-align: center;
    font-size: 11px;
    font-family: 'Fira Code', monospace;
    color: var(--color-muted-foreground);
    outline: none;
  }
  .tk-box-input:focus {
    color: var(--color-foreground);
    background: var(--color-card-hover);
    border-radius: 2px;
  }
  .tk-box-input::placeholder {
    color: var(--color-dim);
    opacity: 0.5;
  }

  .tk-box-top { grid-column: 2; grid-row: 1; justify-self: center; }
  .tk-box-right { grid-column: 3; grid-row: 2; align-self: center; justify-self: end; }
  .tk-box-bottom { grid-column: 2; grid-row: 3; justify-self: center; }
  .tk-box-left { grid-column: 1; grid-row: 2; align-self: center; justify-self: start; }
</style>
