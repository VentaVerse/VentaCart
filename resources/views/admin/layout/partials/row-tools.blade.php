{{-- A row's columns and style. $unitExpr and $regionExpr are Alpine
     expressions for the row and its position, so the same controls serve a
     split row's own panel and the Edit panel of a widget in a one-column row. --}}
<div class="v-rowtools">
    <div class="v-rowtools__group">
        <p class="v-rowtools__lbl" id="v-shapes-{{ md5($unitExpr . $regionExpr) }}">Columns: split this row to put widgets side by side</p>
        <span class="v-lrow__shapes" role="group" aria-labelledby="v-shapes-{{ md5($unitExpr . $regionExpr) }}">
            <template x-for="shape in layouts" :key="shape.join('-')">
                <button type="button" class="v-lshape" :class="{ 'is-on': sameShape({{ $unitExpr }}.columns, shape) }"
                        @click="setShapeOf({{ $regionExpr }}, {{ $unitExpr }}, shape)"
                        :aria-pressed="sameShape({{ $unitExpr }}.columns, shape) ? 'true' : 'false'"
                        :aria-label="shapeLabel(shape)" :title="shapeLabel(shape)">
                    <template x-for="(w, i) in shape" :key="i">
                        <i :style="'flex-grow:' + w"></i>
                    </template>
                </button>
            </template>
        </span>
    </div>
    <div class="v-rowtools__acts">
        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="editRowOf({{ $regionExpr }}, {{ $unitExpr }})">
            Row style<span x-show="styledRow({{ $unitExpr }})">: styled</span>
        </button>
        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="moveRowOf({{ $regionExpr }}, {{ $unitExpr }}, -1)"
                :disabled="rowIndex({{ $regionExpr }}, {{ $unitExpr }}) === 0">Move row up</button>
        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="moveRowOf({{ $regionExpr }}, {{ $unitExpr }}, 1)"
                :disabled="rowIndex({{ $regionExpr }}, {{ $unitExpr }}) === rowsIn({{ $regionExpr }}).length - 1">Move row down</button>
        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="toggleRowOf({{ $regionExpr }}, {{ $unitExpr }})"
                x-text="{{ $unitExpr }}.enabled ? 'Hide row' : 'Show row'"></button>
        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="removeRowOf({{ $regionExpr }}, {{ $unitExpr }})">Remove row</button>
    </div>
</div>
